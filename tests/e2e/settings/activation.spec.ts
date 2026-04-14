/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe('plugin activation', () => {
	test('should load the plugins screen', async ({ admin, page }) => {
		await admin.visitAdminPage('plugins.php');

		await expect(
			page.getByRole('heading', { name: 'Plugins', exact: true })
		).toBeVisible();
	});

	test('should show the active plugin and allow deactivation', async ({
		admin,
		page,
	}) => {
		await admin.visitAdminPage('plugins.php');

		const dismissOnboardingModal = async () => {
			const modal = page.locator('#onemedia-site-selection-modal');
			const backdrop = page.locator('body.onemedia-site-selection-modal');

			if (await modal.isVisible()) {
				await modal.evaluate((element) => {
					element.remove();
				});
			}

			if (await backdrop.isVisible()) {
				await backdrop.evaluate((element) => {
					element.classList.remove('onemedia-site-selection-modal');
				});
			}
		};

		const pluginRow = page.locator(
			'tr[data-plugin="onemedia/onemedia.php"]'
		);
		await expect(pluginRow).toBeVisible();
		await expect(
			pluginRow.locator('a', { hasText: 'Deactivate' })
		).toBeVisible();

		await dismissOnboardingModal();

		await Promise.all([
			page.waitForURL(/plugins.php/),
			pluginRow.locator('a', { hasText: 'Deactivate' }).click(),
		]);

		await expect(
			pluginRow.locator('a', { hasText: 'Activate' })
		).toBeVisible({ timeout: 10000 });
	});
});
