# AGENTS.md — How Does That Work?

## What is this project?

A party game inspired by Jackbox Party Packs. The host starts a game on a TV/monitor, and players join by scanning a QR code on their phones. The game gives you a topic — something everyone *should* know how it works but nobody actually does (microwaves, refrigeration, CRT TVs, nuclear fusion, etc.) — and you have to give a speech explaining how it works using your phone's microphone.

The host buys tokens via Stripe, which fund OpenAI API calls. Whisper transcribes your speech, then GPT grades it, generates a report, and roasts you for being wrong. That's the game.

## Tech Stack

- **Backend**: Laravel 12, PHP 8.5, MySQL, Inertia.js
- **Frontend**: Vue 3, Vite 7, Tailwind CSS 4, TypeScript
- **Auth**: Laravel Fortify
- **Payments**: Stripe (for token purchases)
- **AI**: OpenAI — Whisper API for speech-to-text, GPT for grading/report generation
- **Testing**: Pest PHP (backend), Vitest (frontend JS), Playwright (e2e)
- **Linting**: Pint (PHP), ESLint + Prettier (JS/Vue)

## Local Development

- **Site URL**: `https://how-does-that-work.test` (served by Laravel Herd)
- **HTTPS**: Herd manages TLS certs. Run `herd secure how-does-that-work` if certs are missing.
- **Vite dev server**: `npm run dev` — runs on **port 5173** by default. The `detectTls` option in `vite.config.ts` auto-picks up Herd certificates so Vite also serves over HTTPS.
- **Database**: MySQL on localhost, database `how_does_that_work`, user `root`, no password.
- **Tests use SQLite in-memory** (configured in phpunit.xml), not MySQL.

## Deployment

- **AWS EC2**: Instance `i-02155d7de13125a95` (t3.small, Ubuntu 24.04), Elastic IP `18.213.144.0`
- **Domain**: `lordoftongs.com` via Route 53
- **Server stack**: nginx, PHP 8.4 FPM, MySQL 8.0, Node 20
- **SSL**: Let's Encrypt via Certbot, auto-renewing
- **Infrastructure is managed from the local machine** via AWS CLI (already authenticated) and Claude. The EC2 instance itself does not have AWS CLI — SSM secrets are fetched from the local machine and deployed.
- **Deploy path**: `/var/www/lordoftongs`
- **SSH access**: `ssh -i ~/.ssh/lordoftongs-prod ubuntu@18.213.144.0`
- **Merge-to-main hooks** handle deployment to the EC2 instance.

## Key Features Requiring Special APIs

- **Microphone access**: The game requires browser microphone access for speech recording. This is why HTTPS is mandatory — browsers block `getUserMedia` on non-secure origins.
- **Stripe**: Token purchasing to fund AI calls.
- **OpenAI Whisper**: Speech-to-text transcription of player recordings.
- **OpenAI GPT**: Grading speeches and generating humorous reports.

## Working with this codebase

This project is an experiment in building entirely through Claude without the developer ever opening an editor. Use your best judgment and pretty much do everything — write code, run tests, deploy, fix issues. The developer trusts Claude agents to make decisions and execute autonomously.

- Run `./vendor/bin/pint` before committing PHP changes
- Run `composer test` to verify lint + tests pass
- Run `npm run test:js` for frontend tests
- Run `npm run test:e2e` for Playwright e2e tests
- See `progress.md` for detailed codebase patterns, deployment notes, and implementation history
