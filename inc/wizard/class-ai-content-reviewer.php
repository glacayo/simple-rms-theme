<?php
/**
 * Wizard AI content reviewer.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Reviews decoded AI section copy before builder validation and save.
 */
final class AI_Content_Reviewer {
	private const MAX_PASSES = 2;

	private const DIAGNOSES = [
		'generic_copy',
		'semantic_repetition',
		'unsupported_claims',
		'keyword_stuffing',
		'filler_content',
		'missing_trust_signal',
		'missing_differentiator',
		'intent_mismatch',
		'ai_speak',
		'overtechnical_language',
		'repetitive_wording',
		'section_angle_overlap',
		'guardrail_gap',
	];

	private const REWRITE_DIRECTIVES = [
		'generic_copy'         => [
			'soft' => 'Add specific value from the trusted client context and remove copy that could fit any business.',
			'hard' => 'Replace generic phrasing with concrete, supportable customer value. Keep only facts present in client context.',
		],
		'semantic_repetition'  => [
			'soft' => 'Shift this section to a distinct angle and avoid repeating prior section ideas.',
			'hard' => 'Rewrite repeated ideas so this section has a unique job, vocabulary, and customer benefit.',
		],
		'unsupported_claims'   => [
			'soft' => 'Remove or soften unverifiable facts, numbers, locations, credentials, or guarantees.',
			'hard' => 'Delete every unsupported factual claim. Use generic but useful wording when proof is absent.',
		],
		'keyword_stuffing'     => [
			'soft' => 'Make repeated keywords read naturally and prioritize people-first copy.',
			'hard' => 'Remove forced keyword repetition and rewrite for clarity before search phrasing.',
		],
		'filler_content'       => [
			'soft' => 'Cut padding and make each sentence carry useful information.',
			'hard' => 'Replace filler with concise, actionable copy tied to the section purpose.',
		],
		'missing_trust_signal' => [
			'soft' => 'Add a supportable trust cue without inventing proof.',
			'hard' => 'Make trust explicit using only client-provided facts or non-factual reassurance.',
		],
		'missing_differentiator' => [
			'soft' => 'Use a real differentiator only when it exists in trusted client context; otherwise ask for specificity through generic, non-factual copy.',
			'hard' => 'Remove vague differentiation claims and do not invent years, guarantees, brands, licenses, bilingual service, equipment, or credentials.',
		],
		'intent_mismatch'      => [
			'soft' => 'Realign the copy to the layout role and visitor decision point.',
			'hard' => 'Rewrite the section so every field serves its declared purpose.',
		],
		'ai_speak'             => [
			'soft' => 'Replace LLM tics with direct, human language.',
			'hard' => 'Remove generic AI phrasing and write plain, confident copy with specific value.',
		],
		'overtechnical_language' => [
			'soft' => 'Keep credibility, but translate technical method into a clear customer benefit.',
			'hard' => 'Rewrite jargon-heavy or method-first copy so the customer outcome comes first and the technical detail supports it.',
		],
		'repetitive_wording'  => [
			'soft' => 'Vary repeated generic praise words and replace them with concrete, supportable outcomes.',
			'hard' => 'Remove repeated praise adjectives and duplicate phrases across this page; use specific customer value instead.',
		],
		'section_angle_overlap' => [
			'soft' => 'Give this section a distinct job or angle from prior sections.',
			'hard' => 'Rewrite the section around a clearly different purpose such as process, result, customer experience, trust, service overview, or CTA.',
		],
		'guardrail_gap'        => [
			'soft' => 'Fix harness guardrail violations while preserving allowed keys only.',
			'hard' => 'Return only allowed fields and remove blocked, invented, or structurally invalid content.',
		],
	];

	private const PROMPT_CRITIQUE = <<<'PROMPT'
You are the quality reviewer for one WordPress wizard home-page section.

Evaluate the decoded JSON against these primary quality sources only:
- Google Helpful Content: people-first, useful, original content.
- Google Spam Policies: no keyword stuffing or search-engine-first copy.
- Google Search Quality Rater Guidelines sections 2.3-2.6: experience, expertise, authority, trust, and no unsupported claims.
- Google Local Services Ads policies: truthful local-service claims only.
- NNGroup plain-language and succinct-writing guidance: scannable, direct, concise copy.

Do not use secondary readability tools as gates. Use the supplied harness rules as the only source of layout word-count and structural requirements. Allow the spec tolerance of plus/minus 2 to 6 words when naturalness improves; flag larger deviations or padding.

Content calibration checks:
- Flag excessive jargon or technical method-first copy as overtechnical_language when it is not translated into customer benefit.
- Flag paragraphs that open with abstract claims before giving a concrete benefit, outcome, or customer concern.
- Flag repeated generic praise adjectives or duplicate phrases across the current and prior sections as repetitive_wording.
- Flag sections that repeat the same promise, section job, or value angle instead of serving a distinct purpose as section_angle_overlap.
- Flag copy that claims differentiation without a real differentiator in trusted context as missing_differentiator; request specificity but do not invent years, guarantees, brands, licenses, bilingual service, special equipment, credentials, or proof.
- Flag service or SEO headings that should use concrete service/search-intent terms from trusted_client_context.company_services instead of vague quality claims.
- Flag any service-specific language not grounded in trusted_client_context.company_services as unsupported_claims or guardrail_gap.

Return one JSON object only:
{
  "verdict": "pass|fail",
  "diagnoses": ["generic_copy|semantic_repetition|unsupported_claims|keyword_stuffing|filler_content|missing_trust_signal|missing_differentiator|intent_mismatch|ai_speak|overtechnical_language|repetitive_wording|section_angle_overlap|guardrail_gap"],
  "fields": {"field_name": {"status": "pass|fail", "diagnoses": ["..."], "notes": "short reason"}},
  "summary": "short reason"
}

A rewrite is allowed only after at least one valid diagnosis exists.
PROMPT;

	private AI_Content_Harness $harness;

	public function __construct( ?AI_Content_Harness $harness = null ) {
		$this->harness = $harness ?? new AI_Content_Harness();
	}

	/**
	 * @return array{payload:array<string,mixed>,status:string,iterations:int,report:?array}
	 */
	public function review( string $layout, array $decoded, array $prior_sections, array $ai_config ): array {
		if ( ! $this->harness->has_fillable_fields( $layout ) ) {
			return $this->result( $decoded, 'skipped', 0, $this->report( $layout, 'skipped', 0, [], [] ) );
		}

		$original = $decoded;
		$current  = $decoded;
		$history  = [];

		for ( $pass = 1; $pass <= self::MAX_PASSES; $pass++ ) {
			$critique = $this->critique( $layout, $current, $prior_sections, $ai_config );

			if ( null === $critique ) {
				return $this->result( $original, 'fallback', $pass - 1, $this->report( $layout, 'fallback', $pass - 1, [], $history ) );
			}

			$diagnoses = $this->diagnose( $critique );
			$history[] = [
				'pass'      => $pass,
				'verdict'   => (string) ( $critique['verdict'] ?? 'fail' ),
				'diagnoses' => $diagnoses,
				'fields'    => is_array( $critique['fields'] ?? null ) ? $critique['fields'] : [],
			];

			if ( [] === $diagnoses ) {
				$status = 1 === $pass ? 'pass' : 'rewritten';

				return $this->result( $current, $status, $pass - 1, $this->report( $layout, $status, $pass - 1, [], $history ) );
			}

			$rewritten = $this->rewrite( $layout, $current, $prior_sections, $ai_config, $diagnoses, $pass );

			if ( null === $rewritten ) {
				return $this->result( $original, 'fallback', $pass - 1, $this->report( $layout, 'fallback', $pass - 1, $diagnoses, $history ) );
			}

			if ( [] !== $rewritten && ! $this->has_disallowed_keys( $layout, $rewritten ) ) {
				$current = $rewritten;
			} else {
				$history[] = [ 'pass' => $pass, 'verdict' => 'rewrite_discarded', 'diagnoses' => [ 'guardrail_gap' ], 'fields' => [] ];
			}
		}

		return $this->result( $current, 'budget_exhausted', self::MAX_PASSES, $this->report( $layout, 'budget_exhausted', self::MAX_PASSES, $diagnoses ?? [], $history ) );
	}

	private function critique( string $layout, array $payload, array $prior_sections, array $ai_config ): ?array {
		$result = $this->provider( $ai_config )->generate(
			$this->model( $ai_config ),
			$this->critique_prompt( $layout, $payload, $prior_sections, $ai_config ),
			[ 'section_key' => $layout, 'review_stage' => 'critique' ],
			$this->critique_system_prompt()
		);

		if ( empty( $result['success'] ) || empty( $result['content'] ) ) {
			return null;
		}

		$decoded = $this->decode_json( (string) $result['content'] );

		return [] === $decoded ? null : $decoded;
	}

	private function rewrite( string $layout, array $payload, array $prior_sections, array $ai_config, array $diagnoses, int $pass ): ?array {
		$result = $this->provider( $ai_config )->generate(
			$this->model( $ai_config ),
			$this->rewrite_prompt( $layout, $payload, $prior_sections, $ai_config, $diagnoses, $pass ),
			[ 'section_key' => $layout, 'review_stage' => 'rewrite', 'review_pass' => $pass ],
			'Rewrite the JSON copy only. Return one compact JSON object and no markdown.'
		);

		if ( empty( $result['success'] ) || empty( $result['content'] ) ) {
			return null;
		}

		return $this->decode_json( (string) $result['content'] );
	}

	private function provider( array $ai_config ): AI_Provider {
		return AI_Provider_Registry::make_provider( \sanitize_key( (string) ( $ai_config['provider'] ?? '' ) ) );
	}

	private function model( array $ai_config ): string {
		return \sanitize_text_field( (string) ( $ai_config['model'] ?? '' ) );
	}

	private function diagnose( array $critique ): array {
		$diagnoses = [];

		foreach ( (array) ( $critique['diagnoses'] ?? [] ) as $code ) {
			if ( $this->valid_diagnosis( $code ) ) {
				$diagnoses[] = (string) $code;
			}
		}

		foreach ( is_array( $critique['fields'] ?? null ) ? $critique['fields'] : [] as $field ) {
			foreach ( (array) ( is_array( $field ) ? ( $field['diagnoses'] ?? [] ) : [] ) as $code ) {
				if ( $this->valid_diagnosis( $code ) ) {
					$diagnoses[] = (string) $code;
				}
			}
		}

		$diagnoses = array_values( array_unique( $diagnoses ) );

		if ( [] === $diagnoses && 'fail' === (string) ( $critique['verdict'] ?? '' ) ) {
			$diagnoses[] = 'guardrail_gap';
		}

		return $diagnoses;
	}

	private function valid_diagnosis( $code ): bool {
		return is_string( $code ) && in_array( $code, self::DIAGNOSES, true );
	}

	private function critique_system_prompt(): string {
		$prompt = (string) \apply_filters( 'wizard_ai_content_reviewer_critique_prompt', self::PROMPT_CRITIQUE );

		return '' === trim( $prompt ) ? self::PROMPT_CRITIQUE : $prompt;
	}

	private function critique_prompt( string $layout, array $payload, array $prior_sections, array $ai_config ): string {
		return $this->json_prompt(
			'Review this section and diagnose failures before any rewrite.',
			$layout,
			$payload,
			$prior_sections,
			$ai_config,
			[]
		);
	}

	private function rewrite_prompt( string $layout, array $payload, array $prior_sections, array $ai_config, array $diagnoses, int $pass ): string {
		$mode       = 1 === $pass ? 'soft' : 'hard';
		$directives = [];

		foreach ( $diagnoses as $diagnosis ) {
			$directives[ $diagnosis ] = self::REWRITE_DIRECTIVES[ $diagnosis ][ $mode ] ?? self::REWRITE_DIRECTIVES['guardrail_gap'][ $mode ];
		}

		return $this->json_prompt( 'Rewrite only the diagnosed failures using these directives.', $layout, $payload, $prior_sections, $ai_config, $directives );
	}

	private function json_prompt( string $instruction, string $layout, array $payload, array $prior_sections, array $ai_config, array $directives ): string {
		$client_context = is_array( $ai_config['client_context'] ?? null ) ? $ai_config['client_context'] : [];

			$data = [
				'instruction'          => $instruction,
				'layout'               => $layout,
				'allowed_keys'         => $this->harness->get_fillable_fields( $layout ),
				'harness_rules'        => AI_Content_Harness::get_editorial_rules( $layout ),
				'trusted_client_context' => $client_context,
				'current_payload'      => $payload,
				'prior_sections'       => array_values( $prior_sections ),
				'rewrite_directives'   => $directives,
				'output_contract'      => 'Return one JSON object using only allowed_keys. Preserve valid fields unless a directive requires change.',
				'negative_constraints' => 'Do not invent facts, URLs, media, credentials, years, guarantees, brands, licenses, bilingual service, special equipment, statistics, service areas, reviews, keys, repeater subfields, or services absent from trusted_client_context.company_services.',
			];

			$keyword_intent = $this->declared_keyword_intent( $ai_config );

			if ( [] !== $keyword_intent ) {
				$data['declared_keyword_intent'] = $keyword_intent;
			}

		$json = \wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return false === $json ? '{}' : $json;
	}

		/**
		 * @param array<string,mixed> $ai_config Review config.
		 *
		 * @return array<string,mixed>
		 */
		private function declared_keyword_intent( array $ai_config ): array {
			$raw = is_array( $ai_config['keyword_intent'] ?? null ) ? $ai_config['keyword_intent'] : [];
			$primary = trim( (string) ( $raw['primary_keyword'] ?? '' ) );

			if ( '' === $primary ) {
				return [];
			}

			$secondary = is_array( $raw['secondary_keywords'] ?? null )
				? $raw['secondary_keywords']
				: ( is_array( $raw['subkeywords'] ?? null ) ? $raw['subkeywords'] : [] );

			return [
				'primary_keyword'     => $primary,
				'secondary_keywords'  => array_values( array_map( 'strval', $secondary ) ),
				'role'                => 'Editorial search intent for this homepage section only. Not evidence. Distinguish natural target-keyword usage from keyword stuffing and flag intent mismatch. Do not invent services, locations, credentials, guarantees, statistics, or business facts.',
			];
		}

		private function decode_json( string $content ): array {
		$content = trim( preg_replace( '/^```(?:json)?|```$/m', '', $content ) ?? $content );
		$data    = json_decode( $content, true );

		return is_array( $data ) ? $data : [];
	}

	private function has_disallowed_keys( string $layout, array $payload ): bool {
		$allowed_top = array_flip( $this->harness->get_fillable_fields( $layout ) );
		$repeaters   = $this->get_repeater_field_contracts( $layout );

		foreach ( $payload as $key => $value ) {
			$key = (string) $key;

			if ( ! isset( $allowed_top[ $key ] ) ) {
				return true;
			}

			if ( isset( $repeaters[ $key ] ) && $this->repeater_has_disallowed_keys( $value, $repeaters[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_repeater_field_contracts( string $layout ): array {
		$repeaters   = $this->harness->get_text_repeater_fields( $layout );
		$fillable    = array_flip( $this->harness->get_fillable_fields( $layout ) );
		$rules       = AI_Content_Harness::get_editorial_rules( $layout );
		$field_rules = is_array( $rules['fields'] ?? null ) ? $rules['fields'] : [];
		$repeater    = '';

		foreach ( $field_rules as $field => $field_rule ) {
			$field = (string) $field;

			if ( isset( $fillable[ $field ] ) ) {
				$repeater = is_array( $field_rule ) && array_key_exists( 'rows', $field_rule ) && ! isset( $repeaters[ $field ] ) ? $field : '';

				continue;
			}

			if ( '' === $repeater ) {
				continue;
			}

			$repeaters[ $repeater ][] = $field;
		}

		return $repeaters;
	}

	private function repeater_has_disallowed_keys( $rows, array $allowed_subfields ): bool {
		$allowed = array_flip( $allowed_subfields );

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( ! is_array( $row ) ) {
				return true;
			}

			foreach ( $row as $subfield => $value ) {
				unset( $value );

				if ( ! isset( $allowed[ (string) $subfield ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function report( string $layout, string $status, int $iterations, array $diagnoses, array $history ): ?array {
		if ( ! \defined( 'WP_DEBUG' ) || true !== \constant( 'WP_DEBUG' ) ) {
			return null;
		}

		return [
			'layout'     => $layout,
			'status'     => $status,
			'iterations' => $iterations,
			'diagnoses'  => array_values( array_unique( $diagnoses ) ),
			'history'    => $history,
		];
	}

	private function result( array $payload, string $status, int $iterations, ?array $report ): array {
		return [
			'payload'    => $payload,
			'status'     => $status,
			'iterations' => $iterations,
			'report'     => $report,
		];
	}
}
