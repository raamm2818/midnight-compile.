import crypto from 'node:crypto';
import { getSql } from './_db.js';

const COOKIE_NAME = 'hackathon_admin';
const SESSION_SECONDS = 60 * 60 * 12;

function json(res, status, body) { res.status(status).json(body); }
function csvCell(value) {
  let text = String(value ?? '');
  if (/^[=+\-@]/.test(text)) text = `'${text}`;
  return `"${text.replace(/"/g, '""')}"`;
}
function registrationsCsv(teams) {
  const rows = [['Team Name', 'Leader Name', 'Problem Statement ID', 'Member Names', 'Member Classes', 'Registered At']];
  teams.forEach(team => rows.push([
    team.team_name,
    team.leader_name,
    team.problem_statement_id,
    (team.members || []).map(member => member.name).join(' | '),
    (team.members || []).map(member => member.className).join(' | '),
    team.created_at,
  ]));
  return `\uFEFF${rows.map(row => row.map(csvCell).join(',')).join('\r\n')}`;
}
function secret() { return process.env.SESSION_SECRET || ''; }
function makeToken() {
  const payload = Buffer.from(JSON.stringify({ exp: Math.floor(Date.now() / 1000) + SESSION_SECONDS })).toString('base64url');
  const signature = crypto.createHmac('sha256', secret()).update(payload).digest('base64url');
  return `${payload}.${signature}`;
}
function isAuthorized(req) {
  if (!secret()) return false;
  const match = (req.headers.cookie || '').match(new RegExp(`(?:^|; )${COOKIE_NAME}=([^;]+)`));
  if (!match) return false;
  const [payload, signature] = match[1].split('.');
  if (!payload || !signature) return false;
  const expected = crypto.createHmac('sha256', secret()).update(payload).digest('base64url');
  if (signature.length !== expected.length || !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) return false;
  try { return JSON.parse(Buffer.from(payload, 'base64url').toString()).exp > Date.now() / 1000; } catch { return false; }
}
function clearCookie(res) { res.setHeader('Set-Cookie', `${COOKIE_NAME}=; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=0`); }

export default async function handler(req, res) {
  if (req.method === 'POST' && req.body?.action === 'login') {
    const password = String(req.body.password || '');
    const configured = process.env.ADMIN_PASSWORD || '';
    if (!configured || !secret()) return json(res, 500, { success: false, message: 'Admin authentication is not configured.' });
    if (password.length !== configured.length || !crypto.timingSafeEqual(Buffer.from(password), Buffer.from(configured))) {
      return json(res, 401, { success: false, message: 'Wrong password.' });
    }
    res.setHeader('Set-Cookie', `${COOKIE_NAME}=${makeToken()}; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=${SESSION_SECONDS}`);
    return json(res, 200, { success: true });
  }

  if (req.method === 'POST' && req.body?.action === 'logout') {
    clearCookie(res);
    return json(res, 200, { success: true });
  }

  if (req.method !== 'GET') return json(res, 405, { success: false, message: 'Invalid request method.' });
  if (!isAuthorized(req)) return json(res, 401, { success: false, message: 'Unauthorized.' });

  try {
    const sql = getSql();
    const teams = await sql`
      SELECT r.id, r.team_name, r.leader_name, r.problem_statement_id, r.created_at,
        COALESCE(json_agg(json_build_object('name', m.member_name, 'className', m.member_class) ORDER BY m.position)
          FILTER (WHERE m.id IS NOT NULL), '[]') AS members
      FROM registrations r
      LEFT JOIN team_members m ON m.registration_id = r.id
      GROUP BY r.id
      ORDER BY r.created_at DESC
    `;
    if (req.query?.export === 'csv') {
      res.setHeader('Content-Type', 'text/csv; charset=utf-8');
      res.setHeader('Content-Disposition', 'attachment; filename="hackthon-registrations.csv"');
      res.setHeader('Cache-Control', 'no-store');
      return res.status(200).send(registrationsCsv(teams));
    }
    return json(res, 200, { success: true, teams });
  } catch (error) {
    console.error('Admin database error:', error);
    return json(res, 500, { success: false, message: 'Could not load registrations.' });
  }
}
