import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
    test('loads and renders key elements', async ({ page }) => {
        await page.goto('/');

        // Page title
        await expect(page).toHaveTitle(/How Does That Work/);

        // App name / hero heading
        await expect(page.locator('h1')).toContainText('How Does That Work?');

        // Navigation links (login/register for unauthenticated users)
        await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Register' })).toBeVisible();

        // Join game input and button
        await expect(page.getByPlaceholder('Game code')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Join' })).toBeVisible();

        // Host a Game CTA
        await expect(page.getByRole('link', { name: 'Host a Game' })).toBeVisible();

        // How to Play section
        await expect(page.getByText('How to Play')).toBeVisible();
        await expect(page.getByText('Submit Your Topics')).toBeVisible();
        await expect(page.getByText('Get Graded by AI')).toBeVisible();
    });
});
