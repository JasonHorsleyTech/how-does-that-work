import { test, expect } from '@playwright/test';
import { loginAs, expectUrl } from './helpers';

// Deterministic user IDs after `migrate:fresh --seed`
// (DatabaseSeeder creates test@example.com as ID 1, then DevSeeder creates dev users)
const HOST_STANDARD = 3; // host-standard@dev.test — lobby game (LOBBY1)
const HOST_SUBMITTING = 6; // host-submitting@dev.test — submitting game (SUBMIT)
const HOST_READY = 7; // host-ready@dev.test — all-submitted game (READY1)
const HOST_CHOOSING = 8; // host-choosing@dev.test — playing/choosing game (CHOOSE)
const HOST_GRADING_DONE = 9; // host-grading-done@dev.test — grading_complete game (GRADED)
const HOST_ROUND_DONE = 10; // host-round-done@dev.test — round_complete game (RNDDNE)

// Deterministic game codes from DevSeeder
const LOBBY_CODE = 'LOBBY1';
const SUBMIT_CODE = 'SUBMIT';
const READY_CODE = 'READY1';
const CHOOSE_CODE = 'CHOOSE';
const GRADED_CODE = 'GRADED';
const ROUND_DONE_CODE = 'RNDDNE';

test('host creates a new game from dashboard', async ({ page }) => {
    await loginAs(page, HOST_STANDARD);

    // Click "Host a Game" from the dashboard
    await page.getByRole('link', { name: 'Host a Game' }).click();
    await expect(page).toHaveURL(/\/games\/create/);

    // Select 1 round and create the game
    await page.getByRole('button', { name: 'Create Game' }).click();

    // Should redirect to the lobby page with a game code
    await expect(page).toHaveURL(/\/games\/[A-Z0-9]+\/lobby/);

    // Game code should be displayed prominently
    const gameCode = page.locator('text=/^[A-Z0-9]{5,6}$/');
    await expect(gameCode).toBeVisible();

    // Host should appear in the player list (scope to main content to avoid sidebar match)
    const main = page.getByRole('main');
    await expect(main.getByText('Host Standard')).toBeVisible();
    await expect(main.getByText('Host', { exact: true })).toBeVisible();
});

test('host starts submission phase from lobby', async ({ page }) => {
    await loginAs(page, HOST_STANDARD);
    await page.goto(`/games/${LOBBY_CODE}/lobby`);

    await page.getByRole('button', { name: 'Start Submission Phase' }).click();

    await expectUrl(page, `/games/${LOBBY_CODE}/submit`);
});

test('host submits topics on submit page', async ({ page }) => {
    await loginAs(page, HOST_SUBMITTING);
    await page.goto(`/games/${SUBMIT_CODE}/submit`);

    // Fill in 3 topic inputs
    await page.locator('#topic-1').fill('How does a toaster brown bread evenly?');
    await page.locator('#topic-2').fill('How does a submarine navigate underwater?');
    await page.locator('#topic-3').fill('How does a calculator perform multiplication?');

    await page.getByRole('button', { name: 'Submit Topics' }).click();

    // After submission, stays on the same submit page
    await expectUrl(page, `/games/${SUBMIT_CODE}/submit`);
    // Should show the "Topics submitted!" confirmation
    await expect(page.locator('text=Topics submitted!')).toBeVisible();
});

test('host starts game when all topics submitted', async ({ page }) => {
    await loginAs(page, HOST_READY);
    await page.goto(`/games/${READY_CODE}/submit`);

    await page.getByRole('button', { name: 'Start Game' }).click();

    await expectUrl(page, `/games/${READY_CODE}/play`);
});

test('host advances turn from results page', async ({ page }) => {
    await loginAs(page, HOST_GRADING_DONE);
    // Navigate to any game URL — middleware redirects to results page
    await page.goto(`/games/${GRADED_CODE}/play`);
    // Should be redirected to the results page for the completed turn
    await expect(page).toHaveURL(new RegExp(`/games/${GRADED_CODE}/results/\\d+`));

    await page.getByRole('button', { name: 'Next Player →' }).click();

    await expectUrl(page, `/games/${GRADED_CODE}/play`);
});

test('host reconnects from dashboard via Rejoin button', async ({ page }) => {
    await loginAs(page, HOST_CHOOSING);
    // Dashboard should show a Rejoin link for the playing game
    const rejoinLink = page.getByRole('link', { name: 'Rejoin' });
    await expect(rejoinLink).toBeVisible();

    await rejoinLink.click();

    await expectUrl(page, `/games/${CHOOSE_CODE}/play`);
});

test('guest player sees correct screen on refresh', async ({ page }) => {
    // Join the round_complete game as a guest via dev route
    await page.goto(`/dev/join-game/${ROUND_DONE_CODE}`);
    // Dev route redirects to /lobby, middleware redirects to /round-complete
    await expectUrl(page, `/games/${ROUND_DONE_CODE}/round-complete`);

    // Simulate a refresh by navigating to the wrong page
    await page.goto(`/games/${ROUND_DONE_CODE}/lobby`);
    // Middleware should redirect back to the correct screen
    await expectUrl(page, `/games/${ROUND_DONE_CODE}/round-complete`);
});
