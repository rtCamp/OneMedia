<?php
/**
 * Tests for REST core helpers.
 *
 * @package OneMedia\Tests\Unit\Modules\Core
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Core;

use OneMedia\Modules\Core\Rest;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @covers \OneMedia\Modules\Core\Rest
 */
#[CoversClass( Rest::class )]
final class RestTest extends TestCase {
	/**
	 * Tests hook registration.
	 */
	public function test_register_hooks_registers_cors_filter(): void {
		$rest = new Rest();

		$rest->register_hooks();

		$this->assertSame( 10, has_filter( 'rest_allowed_cors_headers', [ $rest, 'allowed_cors_headers' ] ) );
	}

	/**
	 * Tests that the OneMedia token header is added once.
	 */
	public function test_allowed_cors_headers_adds_onemedia_token_once(): void {
		$rest = new Rest();

		$this->assertSame(
			[ 'X-WP-Nonce', 'X-OneMedia-Token' ],
			$rest->allowed_cors_headers( [ 'X-WP-Nonce' ] )
		);

		$this->assertSame(
			[ 'X-OneMedia-Token' ],
			$rest->allowed_cors_headers( [ 'X-OneMedia-Token' ] )
		);
	}
}
