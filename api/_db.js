import postgres from 'postgres';

let sql;

export function getSql() {
  if (!process.env.DATABASE_URL) {
    const error = new Error('DATABASE_URL is not configured.');
    error.code = 'DATABASE_NOT_CONFIGURED';
    throw error;
  }

  // Supabase's transaction pooler is intended for serverless functions.
  // One connection per warm function instance prevents connection exhaustion.
  if (!sql) {
    sql = postgres(process.env.DATABASE_URL, {
      max: 1,
      prepare: false,
      ssl: 'require',
    });
  }
  return sql;
}
