<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class PHPStanAnalysisResult
{
    /**
     * @param list<Error> $errors
     * @param list<CollectedData> $collectedData
     * @param list<string> $notFileSpecificErrors
     * @param null|string $agentInstructions The instructions for AI agents that PHPStan printed after its errors
     */
    public function __construct(
        public int $exitCode,
        public array $errors,
        public array $collectedData,
        public array $notFileSpecificErrors,
        public ?string $agentInstructions = null,
    ) {}

    public function withAgentInstructions(?string $agentInstructions): self
    {
        return new self(
            $this->exitCode,
            $this->errors,
            $this->collectedData,
            $this->notFileSpecificErrors,
            $agentInstructions,
        );
    }
}
