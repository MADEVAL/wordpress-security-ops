<?php
/**
 * JSON output helpers.
 */

declare(strict_types=1);

/**
 * Build a stable response envelope.
 *
 * @param array<string,mixed> $data Payload.
 * @return array<string,mixed>
 */
function secops_envelope( array $data ): array {
	return array_merge(
		array(
			'ok'      => true,
			'at'      => secops_now(),
			'version' => SECOPS_VERSION,
		),
		$data
	);
}

/**
 * Print a JSON envelope.
 *
 * @param array<string,mixed> $data Payload.
 */
function secops_emit( array $data, int $exit_code = SECOPS_EXIT_OK ): void {
	secops_json_response( secops_envelope( $data ), $exit_code );
}

/**
 * Print one JSON object per line.
 *
 * @param array<string,mixed> $data Payload.
 */
function secops_emit_line( array $data ): void {
	echo json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
}
