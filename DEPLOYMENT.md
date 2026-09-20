# Deploy to Vercel with Supabase

This version uses Vercel Node.js Functions and Supabase Postgres. The legacy PHP/MySQL files are deliberately excluded from Vercel deployments.

1. Create a free [Supabase](https://supabase.com) project and copy its Transaction pooler connection string.
2. In the Supabase SQL Editor, run `schema.postgres.sql`.
3. Put the project in a GitHub repository and import that repository in [Vercel](https://vercel.com/new).
4. In Vercel, open **Project Settings → Environment Variables** and add these for Production, Preview, and Development:
   - `DATABASE_URL`: the Supabase Transaction pooler connection string
   - `ADMIN_PASSWORD`: a new long private password (do not use `1308`)
   - `SESSION_SECRET`: a random secret of at least 32 characters
5. Deploy. Vercel runs `npm install` automatically using `package.json`.
6. Test `/register.html`, submit a team, then click **Rash** in the footer and log in through `/admin.html`.

Do not add database credentials to `config.php` for Vercel. Vercel environment variables keep them out of the deployed source code.
