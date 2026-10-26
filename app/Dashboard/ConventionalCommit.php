<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * A commit message read as a Conventional Commit ("feat(api)!: subject"), so
 * the deployments changelog can group a release's commits. A message that
 * does not follow the convention lands in other with its first line as the
 * subject.
 */
final readonly class ConventionalCommit
{
    public function __construct(
        public string $type,
        public ?string $scope,
        public bool $isBreaking,
        public string $subject,
    ) {}

    public static function fromMessage(string $message): self
    {
        $lines = preg_split('/\R/', mb_trim($message)) ?: [''];
        $firstLine = mb_trim($lines[0]);
        $mentionsBreakingChange = preg_match('/^BREAKING[ -]CHANGE:/m', $message) === 1;

        if (preg_match('/^(?<type>[a-z]+)(?:\((?<scope>[^)]+)\))?(?<bang>!)?:\s*(?<subject>.+)$/i', $firstLine, $match) !== 1) {
            return new self('other', null, $mentionsBreakingChange, $firstLine);
        }

        return new self(
            mb_strtolower($match['type']),
            $match['scope'] !== '' ? $match['scope'] : null,
            $match['bang'] === '!' || $mentionsBreakingChange,
            mb_trim($match['subject']),
        );
    }

    /**
     * The changelog section the commit is listed under.
     *
     * @return 'breaking'|'features'|'fixes'|'other'
     */
    public function group(): string
    {
        return match (true) {
            $this->isBreaking => 'breaking',
            $this->type === 'feat' => 'features',
            $this->type === 'fix' => 'fixes',
            default => 'other',
        };
    }
}
