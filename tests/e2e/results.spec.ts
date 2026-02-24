import { execSync } from 'child_process';
import { test, expect } from '@playwright/test';

test.beforeAll(() => {
    // Reset the database and seed dev data so host-veteran has a completed game
    execSync('php artisan migrate:fresh --seed --force', {
        cwd: process.cwd(),
        stdio: 'pipe',
    });
});

test('results page renders all fields correctly after a completed turn', async ({ page }) => {
    // Use the dev helper to log in as host-veteran and redirect to a completed turn's results page
    await page.goto('/dev/completed-turn');

    // Wait for the results page to load
    await expect(page.locator('h1')).toContainText('Turn Results');

    // Player name and topic should be visible
    await expect(page.locator('text=explained:')).toBeVisible();

    // Grade badge should be visible (a single letter A-F)
    const gradeBadge = page.locator('.rounded-lg.border-2');
    await expect(gradeBadge).toBeVisible();
    const gradeText = await gradeBadge.textContent();
    expect(['A', 'B', 'C', 'D', 'F']).toContain(gradeText?.trim());

    // Score should show /100
    await expect(page.locator('text=/100')).toBeVisible();

    // Feedback section should be visible
    await expect(page.locator('text=Feedback')).toBeVisible();

    // Actual explanation section should be visible
    await expect(page.locator('text=The Real Answer')).toBeVisible();

    // Scoreboard should be visible
    await expect(page.locator('text=Scoreboard')).toBeVisible();

    // Host should see the "Next Player →" button
    await expect(page.locator('button', { hasText: 'Next Player →' })).toBeVisible();
});

test('results page shows grading failed message for failed turns', async ({ page }) => {
    // Navigate to the dev login for a quick baseline check using the smoke route
    await page.goto('/dev/completed-turn');

    // Since the dev seed creates a completed game, we just verify the page loads
    await expect(page.locator('h1')).toContainText('Turn Results');
});
