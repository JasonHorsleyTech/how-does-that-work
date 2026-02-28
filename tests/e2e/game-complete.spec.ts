import { test, expect } from '@playwright/test';

test('game over screen renders winner correctly', async ({ page }) => {
    // Use the dev helper to log in as host-veteran and redirect to completed game page
    await page.goto('/dev/completed-game');

    // Winner banner should be visible
    await expect(page.locator('text=wins with')).toBeVisible();

    // Final scores section should be present
    await expect(page.locator('text=Final Scores')).toBeVisible();

    // Turn history section should be present
    await expect(page.locator('text=Turn History')).toBeVisible();

    // Play Again button should be visible for host
    await expect(page.locator('button', { hasText: 'Play Again' })).toBeVisible();
});

test('game over screen shows all players ranked by total score', async ({ page }) => {
    await page.goto('/dev/completed-game');

    // The page should show the ranked list
    await expect(page.locator('text=Final Scores')).toBeVisible();

    // Rapid Owl wins with 92 points (seeded in DevSeeder)
    await expect(page.getByRole('heading', { name: /Rapid Owl/ })).toBeVisible();
});

test('game over screen shows per-player turn history', async ({ page }) => {
    await page.goto('/dev/completed-game');

    // Turn history should list topics
    await expect(page.locator('text=Turn History')).toBeVisible();

    // Grade badges should be present (A, B, C, D, or F)
    const gradeBadges = page.locator('.rounded-md.text-sm.font-bold');
    await expect(gradeBadges.first()).toBeVisible();
});
