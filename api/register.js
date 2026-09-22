import { getSql } from './_db.js';

function send(res, status, success, message) {
  res.status(status).json({ success, message });
}

function databaseSetupMessage(error) {
  switch (error && error.code) {
    case '42P01':
    case '42703':
      return 'Registration database is not ready yet. Run schema.postgres.sql in the Supabase SQL Editor, then try again.';
    case '28P01':
      return 'Registration database credentials were rejected. Update DATABASE_URL in Vercel and redeploy.';
    case '3D000':
      return 'The database named in DATABASE_URL does not exist. Update DATABASE_URL in Vercel and redeploy.';
    case '42501':
      return 'The database user does not have permission to save registrations. Use the Supabase connection string in DATABASE_URL.';
    case 'ECONNREFUSED':
    case 'ENOTFOUND':
    case 'ETIMEDOUT':
    case 'ECONNRESET':
    case '08001':
    case '08006':
    case '57P01':
    case '53300':
      return 'Registration database could not be reached. Verify the Supabase DATABASE_URL in Vercel and redeploy.';
    default:
      return null;
  }
}

export default async function handler(req, res) {
  if (req.method !== 'POST') return send(res, 405, false, 'Invalid request method.');

  const input = req.body && typeof req.body === 'object' ? req.body : {};
  const teamName = String(input.team_name || '').trim();
  const leaderName = String(input.leader_name || '').trim();
  const problemStatementId = String(input.problem_statement_id || '').trim();
  const sourceMembers = Array.isArray(input.members) ? input.members : [];
  const errors = [];

  if (!teamName || teamName.length > 150) errors.push('Team name is required.');
  if (!leaderName || leaderName.length > 150) errors.push('Team leader name is required.');
  if (!problemStatementId || problemStatementId.length > 100) errors.push('Problem statement ID is required.');

  const members = [];
  for (const member of sourceMembers) {
    if (!member || typeof member !== 'object') { errors.push('One of the member entries is malformed.'); continue; }
    const name = String(member.name || '').trim();
    const className = String(member.class_name || '').trim();
    if (!name && !className) continue;
    if (!name || !className) { errors.push('Each member needs both a name and a class.'); continue; }
    if (name.length > 150 || className.length > 100) { errors.push('A member detail is too long.'); continue; }
    members.push({ name, className });
  }
  if (members.length !== 5) errors.push('A team must have exactly 5 members plus the leader.');
  if (errors.length) return send(res, 422, false, errors.join(' '));

  try {
    const sql = getSql();
    // Keep the team and roster together while avoiding a fragile JSON query.
    await sql.begin(async (transaction) => {
      const [registration] = await transaction`
        INSERT INTO registrations (team_name, leader_name, problem_statement_id)
        VALUES (${teamName}, ${leaderName}, ${problemStatementId})
        RETURNING id
      `;
      for (const [position, member] of members.entries()) {
        await transaction`
          INSERT INTO team_members (registration_id, member_name, member_class, position)
          VALUES (${registration.id}, ${member.name}, ${member.className}, ${position})
        `;
      }
    });
    return send(res, 201, true, 'Team registered successfully.');
  } catch (error) {
    if (error && error.code === '23505') return send(res, 409, false, 'That team name is already registered.');
    if (error && error.code === 'DATABASE_NOT_CONFIGURED') {
      return send(res, 503, false, 'Registration is not configured yet. The organizer must add DATABASE_URL in Vercel and redeploy.');
    }
    const setupMessage = databaseSetupMessage(error);
    if (setupMessage) return send(res, 503, false, setupMessage);
    console.error('Registration database error:', error);
    const databaseCode = typeof error?.code === 'string' && /^[A-Z0-9_]{1,12}$/.test(error.code)
      ? ` (database code: ${error.code})`
      : '';
    return send(res, 500, false, `Registration could not be saved${databaseCode}. Check the Vercel Function logs.`);
  }
}
