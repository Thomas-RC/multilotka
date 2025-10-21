<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Multilotka\Core\ClickHouseConnection;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Tip\ComboStatisticsRepository;
use Multilotka\Tip\TipBatch;
use Multilotka\Tip\TipBatchRepository;
use Multilotka\Tip\TipCombination;
use Multilotka\Tip\TipCombinationRepository;
use Multilotka\Tip\TipGenerator;
use Twig\Environment;

final class TipController
{
    private readonly TipGenerator $generator;
    private readonly TipBatchRepository $batchRepository;
    private readonly TipCombinationRepository $combinationRepository;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        // Inicjalizacja klienta ClickHouse z .env
        $clickhouse = ClickHouseConnection::create();

        $statsRepository = new ComboStatisticsRepository($clickhouse);
        $this->generator = new TipGenerator($statsRepository);
        $this->batchRepository = new TipBatchRepository($connection);
        $this->combinationRepository = new TipCombinationRepository($connection);
    }

    /**
     * POST /dashboard/tips/generate
     * Generuje nowy zestaw tipów
     */
    public function generate(): string|RedirectResponse
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        try {
            // Generuj tipy
            $scoredCombinations = $this->generator->generateTips();

            if (empty($scoredCombinations)) {
                $this->session->flash('error', 'Nie udało się wygenerować tipów. Spróbuj ponownie.');

                return new RedirectResponse('/dashboard/tips');
            }

            // Oblicz średnią confidence
            $totalConfidence = array_reduce(
                $scoredCombinations,
                fn($sum, $combo) => $sum + $combo->confidence(),
                0.0
            );
            $averageConfidence = round($totalConfidence / count($scoredCombinations), 2);

            // Pobierz ostatni import_job_id
            $lastImportJobId = $this->getLastSuccessfulImportJobId();

            // Utwórz batch
            $batch = TipBatch::create(
                userId: (int) $userId,
                importJobId: $lastImportJobId,
                confidenceFloor: 50.0,
                generatedAt: new DateTimeImmutable('now'),
                notes: 'Automatycznie wygenerowany zestaw tipów',
            );

            $batch = $batch->withAverageConfidence($averageConfidence);
            $batch = $this->batchRepository->save($batch);

            // Zapisz kombinacje
            $combinations = [];
            foreach ($scoredCombinations as $index => $scored) {
                $combinations[] = TipCombination::create(
                    tipBatchId: $batch->id(),
                    sequence: $index + 1,
                    combo: $scored->combo(),
                    confidence: $scored->confidence(),
                    dueScore: $scored->dueScore(),
                    frequencyScore: $scored->frequencyScore(),
                    gapScore: $scored->gapScore(),
                );
            }

            $this->combinationRepository->saveMany($combinations);

            // Opublikuj batch
            $batch = $batch->publish();
            $this->batchRepository->save($batch);

            $this->session->flash('success', sprintf(
                'Wygenerowano %d tipów ze średnią pewnością %.2f%%',
                count($scoredCombinations),
                $averageConfidence
            ));

            return new RedirectResponse('/dashboard/tips');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Błąd generowania tipów: ' . $e->getMessage());

            return new RedirectResponse('/dashboard/tips');
        }
    }

    /**
     * GET /dashboard/tips
     * Wyświetla ostatni zestaw tipów
     */
    public function index(): string|RedirectResponse
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        $flashes = $this->session->allFlashes();
        $latestBatch = $this->batchRepository->findLatestByUser((int) $userId);

        if ($latestBatch === null) {
            return $this->twig->render('tips/index.html.twig', [
                'batch' => null,
                'combinations' => [],
                'user_email' => $this->session->get('user_email'),
                'flashes' => $flashes,
            ]);
        }

        $combinations = $this->combinationRepository->findByBatchId($latestBatch->id());

        return $this->twig->render('tips/index.html.twig', [
            'batch' => $latestBatch,
            'combinations' => $combinations,
            'user_email' => $this->session->get('user_email'),
            'flashes' => $flashes,
        ]);
    }

    private function getLastSuccessfulImportJobId(): ?int
    {
        $result = $this->connection->fetchAssociative(
            "SELECT id FROM import_jobs WHERE status = 'succeeded' ORDER BY finished_at DESC LIMIT 1"
        );

        return $result !== false ? (int) $result['id'] : null;
    }
}
