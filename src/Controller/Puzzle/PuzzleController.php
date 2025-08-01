<?php

namespace App\Controller\Puzzle;

use App\Service\PuzzleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

#[Route('/api/game', name: 'api_game_')]
class PuzzleController extends AbstractController
{
    public function __construct(private PuzzleService $puzzleService){}

    #[Route('/puzzle', name: 'create_puzzle', methods: ['POST'])]
    /**
     * @OA\Post(
     *     path="/api/game/puzzle",
     *     summary="Create a new puzzle"
     * )
     */
    public function createPuzzle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['sessionId'] ?? null;

        if (!$sessionId) {
            return $this->json(['error' => 'Session ID is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $puzzle = $this->puzzleService->createPuzzle($sessionId);
            $state = $this->puzzleService->getPuzzleState($sessionId);

            return $this->json($state, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/submit', name: 'submit_word', methods: ['POST'])]
    /**
     * @OA\Post(
     *     path="/api/game/submit",
     *     summary="Submit a word attempt"
     * )
     */
    public function submitWord(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['sessionId'] ?? null;
        $word = $data['word'] ?? null;

        if (!$sessionId || !$word) {
            return $this->json(['error' => 'Student ID and word are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->puzzleService->submitWord($sessionId, $word);
            return $this->json($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            return $this->json(['error' => $e->getMessage()], $statusCode);
        }
    }

    #[Route('/state/{sessionId}', name: 'get_state', methods: ['GET'])]
    /**
     * @OA\Get(
     *     path="/api/game/state/{sessionId}",
     *     summary="Get current puzzle state"
     * )
     */
    public function getPuzzleState(string $sessionId): JsonResponse
    {
        try {
            $state = $this->puzzleService->getPuzzleState($sessionId);
            return $this->json($state, Response::HTTP_OK);
        } catch (\Exception $e) {
            $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            return $this->json(['error' => $e->getMessage()], $statusCode);
        }
    }

    #[Route('/leaderboard', name: 'get_leaderboard', methods: ['GET'])]
    /**
     * @OA\Get(
     *     path="/api/game/leaderboard",
     *     summary="Get top 10 leaderboard"
     * )
     */
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
    /**
     * @OA\Post(
     *     path="/api/game/end",
     *     summary="End the game"
     * )
     */
    public function endGame(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['sessionId'] ?? null;

        if (!$sessionId) {
            return $this->json(['error' => 'Session ID is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->puzzleService->endGame($sessionId);
            return $this->json($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}