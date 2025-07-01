<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    public function __construct(
        private readonly RateLimiterFactory $chatApiLimiter
    ) {}

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request, HttpClientInterface $client): JsonResponse
    {
        // 1) Rate-limit by client IP
        $limiter = $this->chatApiLimiter->create($request->getClientIp());
        $limit   = $limiter->consume(1);

        if (false === $limit->isAccepted()) {
            return new JsonResponse(
                ['error' => 'Rate limit exceeded. Try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        // 2) Decode incoming JSON
        $payload  = json_decode($request->getContent(), true);
        $messages = $payload['messages'] ?? null;

        // 3) Fallback to single-message mode
        if (!is_array($messages)) {
            $userMessage = $request->request->get('message');
            if (!$userMessage) {
                return new JsonResponse(
                    ['error' => 'Missing message'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $messages = [
                [
                    'role'    => 'system',
                    'content' => <<<TXT
You are the GoTix AI assistant. Keep each answer to 1–2 sentences.

Examples:
Q: What is this site?
A: GoTix is a secure event-booking platform where you can find and buy tickets.

Q: When is James Arthur in Singapore?
A: James Arthur performs on Nov 6, 2026. Use the “Order Now” button to book.

Now answer the user’s question briefly.
TXT
                ],
                [
                    'role'    => 'user',
                    'content' => $userMessage,
                ],
            ];
        }

        // 4) Verify OpenAI API key
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey || !str_starts_with($apiKey, 'sk-')) {
            return new JsonResponse(
                ['error' => 'Missing or invalid OpenAI API key'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // 5) Call OpenAI
        try {
            $response = $client->request(
                'POST',
                'https://api.openai.com/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'model'       => 'gpt-3.5-turbo',
                        'temperature' => 0.7,
                        'max_tokens'  => 150,
                        'messages'    => $messages,
                    ],
                ]
            );

            $data  = $response->toArray(false);
            $reply = $data['choices'][0]['message']['content'] ?? null;

            if (!$reply) {
                return new JsonResponse(
                    [
                        'error'      => 'OpenAI returned no valid response',
                        'openai_raw' => $data,
                    ],
                    Response::HTTP_BAD_GATEWAY
                );
            }

            return new JsonResponse(['reply' => $reply]);
        } catch (\Throwable $e) {
            return new JsonResponse(
                [
                    'error'   => 'Failed to connect to OpenAI',
                    'details' => $e->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
