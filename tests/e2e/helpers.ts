import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/**
 * Log in as a specific user via the dev login route.
 * Navigates to /dev/login-as/{userId} and waits for the redirect to complete.
 */
export async function loginAs(page: Page, userId: number): Promise<void> {
    await page.goto(`/dev/login-as/${userId}`);
    await page.waitForURL('**/dashboard');
}

/**
 * Assert the current page URL matches the given pattern.
 * Supports string (exact match) or RegExp patterns.
 */
export async function expectUrl(page: Page, urlPattern: string | RegExp): Promise<void> {
    if (typeof urlPattern === 'string') {
        await expect(page).toHaveURL(urlPattern);
    } else {
        await expect(page).toHaveURL(urlPattern);
    }
}
