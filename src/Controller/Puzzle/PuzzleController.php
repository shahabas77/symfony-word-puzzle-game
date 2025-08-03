<?php

namespace App\Controller\Puzzle;

use App\Service\PuzzleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/game', name: 'api_game_')]
class PuzzleController extends AbstractController
{
    public function __construct(private PuzzleService $puzzleService){}

    #[Route('/puzzle', name: 'create_puzzle', methods: ['POST'])]

    public function startNewPuzzle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $studentName = $data['studentName'] ?? null;

        if (!$studentName) {
            return $this->json(['error' => 'Something went wrong while creating the puzzle. Please try again later.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $puzzle = $this->puzzleService->generatePuzzle($studentName);
            $state = $this->puzzleService->loadPuzzleStatus($studentName);

            return $this->json($state, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/submit', name: 'submit_word', methods: ['POST'])]
    public function submitWord(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $studentName = $data['studentName'] ?? null;
        $word = $data['word'] ?? null;

        if (!$studentName || !$word) {
            return $this->json(['error' => 'Student Name and word are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->puzzleService->submitWord($studentName, $word);
            return $this->json($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            return $this->json(['error' => $e->getMessage()], $statusCode);
        }
    }


    #[Route('/leaderboard', name: 'get_leaderboard', methods: ['GET'])]
    public function getLeaderboard(): JsonResponse
    {
        try {
            $leaderboard = $this->puzzleService->getLeaderboard();
            return $this->json($leaderboard, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/end', name: 'end_game', methods: ['POST'])]
    public function endGame(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $studentName = $data['studentName'] ?? null;

        if (!$studentName) {
            return $this->json(['error' => 'Student Name is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->puzzleService->endGame($studentName);
            return $this->json($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}