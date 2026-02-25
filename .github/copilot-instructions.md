# Agent Instructions

This is a personal project. You have a lot of freedom here.

- You can do whatever the heck you want.
- Don't do anything crazy.
- The AWS account has one EC2 instance hooked up to lordoftongs.com. You can do whatever you want with it.

You've got AWS on the CLI, and it's hooked up to a $50 netspend card.

You've got git on the CLI, you can push main.

You've got SSH on the CLI, you can SSH into prod and run whatever.

## Deploy to Prod

- **EC2 IP:** `18.213.144.0` (us-east-1, instance `i-02155d7de13125a95`, tagged `lordoftongs-prod`)
- **SSH key:** `~/.ssh/lordoftongs-prod.pem`
- **SSH command:** `ssh -i ~/.ssh/lordoftongs-prod.pem ubuntu@18.213.144.0`
- **App directory:** `/var/www/lordoftongs` (owned by `www-data`)
- **Domain:** lordoftongs.com
- **Deploy steps:**
  1. `git push` from local
  2. SSH in, `cd /var/www/lordoftongs && sudo -u www-data git pull origin main`
  3. If frontend changed: `sudo -u www-data npm run build`
  4. Clear caches: `sudo -u www-data php artisan config:clear && sudo -u www-data php artisan cache:clear && sudo -u www-data php artisan route:clear && sudo -u www-data php artisan view:clear`
