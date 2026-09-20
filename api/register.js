import { getSql } from './_db.js';

function send(res, status, success, message) {
  res.status(status).json({ success, message });
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
  if (members.length < 1) errors.push('Add at least one team member.');
  if (members.length > 5) errors.push('A team can have at most 5 members plus the leader.');
  if (errors.length) return send(res, 422, false, errors.join(' '));

  try {
    const sql = getSql();
    const inserted = await sql`
      INSERT INTO registrations (team_name, leader_name, problem_statement_id)
      VALUES (${teamName}, ${leaderName}, ${problemStatementId})
      RETURNING id
    `;
    const registrationId = inserted[0].id;
    await Promise.all(members.map((member, position) => sql`
      INSERT INTO team_members (registration_id, member_name, member_class, position)
      VALUES (${registrationId}, ${member.name}, ${member.className}, ${position})
    `));
    return send(res, 201, true, 'Team registered successfully.');
  } catch (error) {
    if (error && error.code === '23505') return send(res, 409, false, 'That team name is already registered.');
    if (error && error.code === 'DATABASE_NOT_CONFIGURED') {
      return send(res, 503, false, 'Registration is not configured yet. The organizer must add DATABASE_URL in Vercel and redeploy.');
    }
    if (error && error.code === '42P01') {
      return send(res, 503, false, 'Registration database is not ready yet. The organizer must run schema.postgres.sql in Neon.');
    }
    console.error('Registration database error:', error);
    return send(res, 500, false, 'Server error — please try again later.');
  }
}
