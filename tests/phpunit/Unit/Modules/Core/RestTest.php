<?php
/**
 * Tests for the Core\Rest class.
 *
 * @package OneMedia\Tests\Unit\Modules\Core
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Core;

use OneMedia\Modules\Core\Rest;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Core\Rest class.
 */
#[CoversClass( Rest::class )]
final class RestTest extends TestCase {
	/**
	 * Tests register_hooks adds the CORS filter.
	 */
	public function test_register_hooks_adds_cors_filter(): void {
		( new Rest() )->register_hooks();

		$this->assertNotFalse( has_filter( 'rest_allowed_cors_headers' ) );
	}

	/**
	 * Tests the token is appended to an empty headers array.
	 */
	public function test_allowed_cors_headers_adds_token(): void {
		$result = ( new Rest() )->allowed_cors_headers( [] );

		$this->assertContains( 'X-OneMedia-Token', $result );
	}

	/**
	 * Tests the token is not duplicated when already present.
	 */
	public function test_allowed_cors_headers_is_idempotent(): void {
		$rest    = new Rest();
		$headers = $rest->allowed_cors_headers( [] );
		$result  = $rest->allowed_cors_headers( $headers );

		$this->assertCount( 1, array_filter( $result, static fn ( $h ) => 'X-OneMedia-Token' === $h ) );
	}
}
