<?php

namespace App\Service;

use App\Entity\Puzzle;
use App\Entity\Student;
use App\Entity\Submission;
use App\Entity\Scores;
use App\Repository\PuzzleRepository;
use App\Repository\StudentRepository;
use App\Repository\SubmissionRepository;
use App\Repository\ScoresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PuzzleService
{
    private const PUZZLE_LENGTH = 14;
    private const COMMON_LETTERS = 'ETAOINSHRDLUCMFWY';
    private const VOWELS = 'AEIOU';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private WordListService $wordListService,
        private PuzzleRepository $puzzleRepository,
        private StudentRepository $studentRepository,
        private SubmissionRepository $submissionRepository,
        private ScoresRepository $scoresRepository
    ) {
    }

    public function createPuzzle(string $sessionId): Puzzle
    {
        // Check if student already has an active puzzle
        $student = $this->studentRepository->findOneBy(['sessionId' => $sessionId]);

        if ($student && $student->getPuzzle() && $student->getPuzzle()->isActive()) {
            return $student->getPuzzle();
        }

        // Generate a new puzzle string
        $puzzleString = $this->generatePuzzleString();

        // Create new puzzle
        $puzzle = new Puzzle();
        $puzzle->setPuzzleString($puzzleString);

        // Create or update student
        if (!$student) {
            $student = new Student();
            $student->setSessionId($sessionId);
        }

        $student->setPuzzle($puzzle);
        $student->updateLastActivity();

        $this->entityManager->persist($puzzle);
        $this->entityManager->persist($student);
        $this->entityManager->flush();

        return $puzzle;
    }

    public function submitWord(string $sessionId, string $word): array
    {
        $student = $this->studentRepository->findOneBy(['sessionId' => $sessionId]);

        if (!$student || !$student->getPuzzle() || !$student->getPuzzle()->isActive()) {
            throw new NotFoundHttpException('No active puzzle found for this session');
        }

        $puzzle = $student->getPuzzle();
        $word = strtoupper(trim($word));

        // Validate word
        if (empty($word) || strlen($word) < 1) {
            throw new BadRequestHttpException('Word cannot be empty');
        }

        if (strlen($word) > self::PUZZLE_LENGTH) {
            throw new BadRequestHttpException('Word is too long');
        }

        if (!ctype_alpha($word)) {
            throw new BadRequestHttpException('Word must contain only letters');
        }

        // Check if word is already submitted
        $existingSubmission = $this->submissionRepository->findOneBy([
            'puzzle' => $puzzle,
            'word' => $word
        ]);

        if ($existingSubmission) {
            throw new BadRequestHttpException('Word already submitted');
        }

        // Validate it's a real English word
        if (!$this->wordListService->isValidWord($word)) {
            throw new BadRequestHttpException('Not a valid English word');
        }

        // Check if word can be formed from remaining letters
        if (!$puzzle->canUseLetters($word)) {
            throw new BadRequestHttpException('No such word found with remaining letters');
        }

        // Calculate score (1 point per letter)
        $score = strlen($word);

        // Create submission
        $submission = new Submission();
        $submission->setWord($word);
        $submission->setScore($score);
        $submission->setPuzzle($puzzle);

        // Use the letters (remove from remaining)
        $puzzle->useLetters($word);

        // Check if puzzle is complete (no more valid words possible)
        $isComplete = $this->isPuzzleComplete($puzzle);

        if ($isComplete) {
            $puzzle->setIsActive(false);
        }

        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        // Update leaderboard if score is high enough
        $this->updateTopScorers($word, $score);

        return [
            'word' => $word,
            'score' => $score,
            'totalScore' => $puzzle->getTotalScore(),
            'remainingLetters' => $puzzle->getRemainingLetters(),
            'isComplete' => $isComplete,
            'submissionId' => $submission->getId(),
            'puzzleString' => $puzzle->getPuzzleString()
        ];
    }

    public function getPuzzleState(string $sessionId): array
    {
        $student = $this->studentRepository->findOneBy(['sessionId' => $sessionId]);

        if (!$student || !$student->getPuzzle()) {
            throw new NotFoundHttpException('No puzzle found for this session');
        }

        $puzzle = $student->getPuzzle();
        $submissions = $puzzle->getSubmissions();

        return [
            'puzzleString' => $puzzle->getPuzzleString(),
            'remainingLetters' => $puzzle->getRemainingLetters(),
            'totalScore' => $puzzle->getTotalScore(),
            'isActive' => $puzzle->isActive(),
            'submissions' => array_map(fn($submission) => [
                'word' => $submission->getWord(),
                'score' => $submission->getScore(),
                'submittedAt' => $submission->getSubmittedAt()->format('Y-m-d H:i:s')
            ], $submissions->toArray()),
            'createdAt' => $puzzle->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }

    public function getLeaderboard(): array
    {
        // Get top 10 students by total score
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s.sessionId AS student', 'SUM(sub.score) AS totalScore')
            ->from('App\Entity\Submission', 'sub')
            ->join('sub.puzzle', 'p')
            ->join('p.student', 's')
            ->groupBy('s.sessionId')
            ->orderBy('totalScore', 'DESC')
            ->setMaxResults(10);

        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'student' => $row['student'],
            'score' => $row['totalScore']
        ], $results);
    }

    private function generatePuzzleString(): string
    {
        $puzzle = '';

        // Start with 3 random vowels
        $vowels = str_split(self::VOWELS);
        for ($i = 0; $i < 3; $i++) {
            $puzzle .= $vowels[array_rand($vowels)];
        }

        // Fill the rest with common letters
        $remainingLength = self::PUZZLE_LENGTH - 3;
        $commonLetters = str_split(self::COMMON_LETTERS);

        for ($i = 0; $i < $remainingLength; $i++) {
            $puzzle .= $commonLetters[array_rand($commonLetters)];
        }

        // Shuffle the puzzle string to randomize letter positions
        $puzzleArray = str_split($puzzle);
        shuffle($puzzleArray);

        return implode('', $puzzleArray);
    }

    private function isPuzzleComplete(Puzzle $puzzle): bool
    {
        $remaining = $puzzle->getRemainingLetters();

        // If no letters remaining, puzzle is complete
        if (empty($remaining)) {
            return true;
        }

        // Use word list service to check if any valid words can still be formed
        $remainingWords = $this->wordListService->calculateRemainingWords($remaining, 10);

        // If no valid words can be formed, puzzle is complete
        return empty($remainingWords);
    }

    private function updateTopScorers(string $word, int $score): void
    {
        // Check if this word already exists in leaderboard
        $existingEntry = $this->scoresRepository->findOneBy(['word' => $word]);

        if (!$existingEntry) {
            // Check if we need to add to leaderboard
            $lowestScore = $this->scoresRepository->findOneBy([], ['score' => 'ASC']);

            if (!$lowestScore || $score >= $lowestScore->getScore()) {
                $entry = new Scores();
                $entry->setWord($word);
                $entry->setScore($score);

                $this->entityManager->persist($entry);
                $this->entityManager->flush();

                // Keep only top 10 entries
                $this->limitLeaderboard();
            }
        }
    }

    private function limitLeaderboard(): void
    {
        $allEntries = $this->scoresRepository->findBy([], ['score' => 'DESC', 'createdAt' => 'ASC']);

        if (count($allEntries) > 10) {
            $entriesToRemove = array_slice($allEntries, 10);

            foreach ($entriesToRemove as $entry) {
                $this->entityManager->remove($entry);
            }

            $this->entityManager->flush();
        }
    }

    public function endGame(string $sessionId): array
    {
        $student = $this->studentRepository->findOneBy(['sessionId' => $sessionId]);
        $puzzle = $student->getPuzzle();
        $remainingWords = $this->wordListService->calculateRemainingWords($puzzle->getRemainingLetters());
        $totalScore = $puzzle->getTotalScore();
        $puzzle->setIsActive(false);
        $this->entityManager->flush();
        return [
            'remainingWords' => $remainingWords,
            'totalScore' => $totalScore
        ];
    }

}