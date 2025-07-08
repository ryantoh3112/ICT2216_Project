<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as C;

class ChatController extends AbstractController
{
    public function __construct(
        private readonly RateLimiterFactory $chatApiLimiter,
        private readonly HttpClientInterface   $client,
        private readonly ValidatorInterface    $validator
    ) {}

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        // 1) Enforce 5 requests per 10s sliding window, stored in cache.app
        $limiter = $this->chatApiLimiter->create($request->getClientIp());
        $limit   = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfterSec = $limit->getRetryAfter()->getTimestamp() - time();
            return new JsonResponse(
                ['error' => 'Rate limit exceeded. Try again in '.$retryAfterSec.'s.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $retryAfterSec]
            );
        }

        // 2) Decode & validate JSON schema
        $payload = json_decode($request->getContent(), true);
        $violations = $this->validator->validate($payload, [
            new C\Collection([
                'fields'           => [
                    'messages' => new C\Optional([
                        new C\Type('array'),
                        new C\All([
                            new C\Collection([
                                'fields'            => [
                                    'role'    => [
                                        new C\Type('string'),
                                        new C\Choice(['system','user','assistant']),
                                    ],
                                    'content' => [
                                        new C\Type('string'),
                                        new C\Length(['min'=>1,'max'=>2000]),
                                    ],
                                ],
                                'allowExtraFields' => false,
                            ]),
                        ]),
                    ]),
                ],
                'allowExtraFields' => true,
            ]),
        ]);

        if (count($violations) > 0) {
            return new JsonResponse(
                ['error' => 'Invalid payload: '.$violations[0]->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }

        // 3) Fallback to single-message mode
        $messages = $payload['messages'] ?? null;
        if (!is_array($messages)) {
            $text = (string)$request->request->get('message','');
            if ('' === trim($text)) {
                return new JsonResponse(['error'=>'Missing message'], Response::HTTP_BAD_REQUEST);
            }
            $messages = [
                ['role'=>'system', 'content'=><<<TXT
You are the GoTix AI assistant for local Singapore event-goers.
Keep answers professional and concise (1–2 sentences).

Domain:
• Events: theatre, stadium concerts, comedy, arts, sports.
• Display: date/time, venue, seating tiers, pricing.
• Browse: categories, venues, dates.
• Checkout: login + cart; multi-event booking.
• Payments: Stripe only.
• Accounts: 2FA, change-password, purchase-history; cancellations via support.
• Listings: Singapore only, rolling 3-month window.

Behavior:
• Suggest next steps only when asked.
• If no match: “Sorry, I couldn’t find that. Could you try a different date or category?”
• On invalid input (bad date, sold-out): apologize and ask how to amend.
TXT
                ],
                ['role'=>'user','content'=>$text],
            ];
        }

        // 4) Sanitize & moderate
        $final = [];
        foreach ($messages as $m) {
            $safe = strip_tags($m['content']);
            if ($m['role']==='user') {
                $mod = $this->client->request(
                    'POST',
                    'https://api.openai.com/v1/moderations',
                    [
                        'headers'=>[
                            'Authorization'=>'Bearer '.($_ENV['OPENAI_API_KEY']??''),
                            'Content-Type'=>'application/json',
                        ],
                        'json'=>['input'=>$safe],
                    ]
                )->toArray(false);

                if (!empty($mod['results'][0]['flagged'] ?? false)) {
                    return new JsonResponse(['error'=>'Content flagged'], Response::HTTP_BAD_REQUEST);
                }
            }
            $final[] = ['role'=>$m['role'],'content'=>$safe];
        }

        // 5) Verify API key
        $key = $_ENV['OPENAI_API_KEY'] ?? '';
        if (!str_starts_with($key,'sk-')) {
            return new JsonResponse(['error'=>'Invalid API key'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 6) Call Chat API
        try {
            $resp = $this->client->request(
                'POST',
                'https://api.openai.com/v1/chat/completions',
                [
                    'headers'=>[
                        'Authorization'=>'Bearer '.$key,
                        'Content-Type'=>'application/json',
                    ],
                    'json'=>[
                        'model'=>'gpt-3.5-turbo',
                        'temperature'=>0.7,
                        'max_tokens'=>150,
                        'messages'=>$final,
                    ],
                ]
            );
            $data  = $resp->toArray(false);
            $reply = $data['choices'][0]['message']['content'] ?? '';
            if (''===trim($reply)) {
                return new JsonResponse(['error'=>'No response'], Response::HTTP_BAD_GATEWAY);
            }
            return new JsonResponse(['reply'=>htmlspecialchars($reply,ENT_QUOTES)]);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error'=>'OpenAI error','details'=>$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
