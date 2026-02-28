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
 * Assert the current page URL path matches the given pattern.
 * For string paths, converts to a regex so it's protocol-agnostic (http vs https).
 */
export async function expectUrl(page: Page, urlPattern: string | RegExp): Promise<void> {
    if (typeof urlPattern === 'string') {
        // Escape special regex chars in the path, then match against any protocol
        const escaped = urlPattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        await expect(page).toHaveURL(new RegExp(`${escaped}$`));
    } else {
        await expect(page).toHaveURL(urlPattern);
    }
}
