<?php

namespace App\Tests\Service;

use App\Entity\Puzzle;
use App\Entity\Student;
use App\Entity\Submission;
use App\Entity\Scores;
use App\Repository\PuzzleRepository;
use App\Repository\StudentRepository;
use App\Repository\SubmissionRepository;
use App\Repository\ScoresRepository;
use App\Service\PuzzleService;
use App\Service\WordListService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PuzzleServiceTest extends TestCase
{
    private $entityManager;
    private $wordListService;
    private $puzzleRepository;
    private $studentRepository;
    private $submissionRepository;
    private $scoresRepository;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->wordListService = $this->createMock(WordListService::class);
        $this->puzzleRepository = $this->createMock(PuzzleRepository::class);
        $this->studentRepository = $this->createMock(StudentRepository::class);
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->scoresRepository = $this->createMock(ScoresRepository::class);

        $this->service = new PuzzleService(
            $this->entityManager,
            $this->wordListService,
            $this->puzzleRepository,
            $this->studentRepository,
            $this->submissionRepository,
            $this->scoresRepository
        );
    }

    public function testCreatePuzzleReturnsExistingActivePuzzle()
    {
        $student = $this->createMock(Student::class);
        $puzzle = $this->createMock(Puzzle::class);

        $student->method('getPuzzle')->willReturn($puzzle);
        $puzzle->method('isActive')->willReturn(true);
        $this->studentRepository->method('findOneBy')->willReturn($student);

        $result = $this->service->createPuzzle('session-abc');
        $this->assertEquals($puzzle, $result);
    }

    public function testCreatePuzzleCreatesNewWhenNoActiveExist()
    {
        $this->studentRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createPuzzle('session-xyz');
        $this->assertInstanceOf(Puzzle::class, $result);
        $this->assertEquals(14, strlen($result->getPuzzleString()));
    }

    public function testSubmitWordThrowsWhenNoActivePuzzle()
    {
        $this->studentRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->service->submitWord('session-abc', 'test');
    }

    public function testSubmitWordRejectsEmptyWord()
    {
        $student = $this->createConfiguredMock(Student::class, [
            'getPuzzle' => $this->createConfiguredMock(Puzzle::class, ['isActive' => true])
        ]);
        $this->studentRepository->method('findOneBy')->willReturn($student);

        $this->expectException(BadRequestHttpException::class);
        $this->service->submitWord('session-abc', '');
    }

    public function testSubmitWordRejectsNonAlpha()
    {
        $student = $this->createConfiguredMock(Student::class, [
            'getPuzzle' => $this->createConfiguredMock(Puzzle::class, ['isActive' => true])
        ]);
        $this->studentRepository->method('findOneBy')->willReturn($student);

        $this->expectException(BadRequestHttpException::class);
        $this->service->submitWord('session-abc', 'word123');
    }

    public function testSubmitWordRejectsAlreadySubmitted()
    {
        $student = $this->createConfiguredMock(Student::class, [
            'getPuzzle' => $this->createConfiguredMock(Puzzle::class, ['isActive' => true])
        ]);
        $this->studentRepository->method('findOneBy')->willReturn($student);
        $this->submissionRepository->method('findOneBy')->willReturn(new Submission());

        $this->expectException(BadRequestHttpException::class);
        $this->service->submitWord('session-abc', 'hello');
    }

    public function testSubmitWordRejectsIfNotEnglishWord()
    {
        $student = $this->createConfiguredMock(Student::class, [
            'getPuzzle' => $this->createConfiguredMock(Puzzle::class, ['isActive' => true])
        ]);
        $this->studentRepository->method('findOneBy')->willReturn($student);
        $this->submissionRepository->method('findOneBy')->willReturn(null);
        $this->wordListService->method('isValidWord')->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->service->submitWord('session-abc', 'gibberish');
    }

    public function testSubmitWordAcceptsValidWordAndReturnsResult()
    {
        $puzzle = $this->createConfiguredMock(Puzzle::class, [
            'isActive' => true,
            'canUseLetters' => true,
            'getTotalScore' => 10,
            'getRemainingLetters' => 'ABCDE',
            'getPuzzleString' => 'RANDOMPUZZLE',
            'useLetters' => null
        ]);
        $puzzle->method('getSubmissions')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([]));

        $student = $this->createConfiguredMock(Student::class, [
            'getPuzzle' => $puzzle
        ]);
        $this->studentRepository->method('findOneBy')->willReturn($student);
        $this->submissionRepository->method('findOneBy')->willReturn(null);
        $this->wordListService->method('isValidWord')->willReturn(true);
        $this->wordListService->method('calculateRemainingWords')->willReturn([]);

       
        $result = $this->service->submitWord('session-abc', 'ace');
        $this->assertArrayHasKey('word', $result);
        $this->assertEquals('ACE', $result['word']);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('totalScore', $result);
        $this->assertArrayHasKey('submissionId', $result);
        $this->assertFalse($result['isComplete'] ?? false);
    }

    public function testGetPuzzleStateThrowsIfNotFound()
    {
        $this->studentRepository->method('findOneBy')->willReturn(null);
        $this->expectException(NotFoundHttpException::class);
        $this->service->getPuzzleState('non-existent');
    }

    public function testGetPuzzleStateReturnsInfo()
    {
        $puzzle = $this->createMock(Puzzle::class);
        $submission = $this->createConfiguredMock(Submission::class, [
            'getWord' => 'TEST',
            'getScore' => 4,
            'getSubmittedAt' => new \DateTime('2024-01-01 10:00:00')
        ]);
        $puzzle->method('getSubmissions')
            ->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$submission]));
        $puzzle->method('getPuzzleString')->willReturn('ABCDEF');
        $puzzle->method('getRemainingLetters')->willReturn('ABC');
        $puzzle->method('getTotalScore')->willReturn(12);
        $puzzle->method('isActive')->willReturn(true);
        $puzzle->method('getCreatedAt')->willReturn(new \DateTime('2024-01-01 09:00:00'));

        $student = $this->createMock(Student::class);
        $student->method('getPuzzle')->willReturn($puzzle);

        $this->studentRepository->method('findOneBy')->willReturn($student);

        $result = $this->service->getPuzzleState('sess');
        $this->assertEquals('ABCDEF', $result['puzzleString']);
        $this->assertTrue($result['isActive']);
        $this->assertCount(1, $result['submissions']);
        $this->assertEquals('2024-01-01 09:00:00', $result['createdAt']);
    }

    public function testEndGameMarksAsInactiveAndReturnsInfo()
    {
        $puzzle = $this->createConfiguredMock(Puzzle::class, [
            'getRemainingLetters' => 'AB',
            'getTotalScore' => 22,
            'setIsActive' => null
        ]);
        $student = $this->createConfiguredMock(Student::class, [
            'getPuzzle' => $puzzle
        ]);
        $this->studentRepository->method('findOneBy')->willReturn($student);
        $this->wordListService->method('calculateRemainingWords')->willReturn(['A', 'AB']);

        $this->entityManager->expects($this->once())->method('flush');
        $result = $this->service->endGame('sess2');
        $this->assertEquals(['A', 'AB'], $result['remainingWords']);
        $this->assertEquals(22, $result['totalScore']);
    }

}
