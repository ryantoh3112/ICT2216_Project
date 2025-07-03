<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class AdminAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(private RouterInterface $router) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Prevent intercepting the login or access_denied routes
        $currentRoute = $request->attributes->get('_route');
        if (in_array($currentRoute, ['auth_login', 'access_denied'])) {
            return;
        }
        // Only intercept /admin routes
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }
        

        // Get user from JWT injection
        $user = $request->attributes->get('jwt_user');

        if (!$user) {
            $event->setResponse(new RedirectResponse($this->router->generate('auth_login')));
            return;
        }

        if ($user->getRole() !== 'ROLE_ADMIN') {
            $event->setResponse(new RedirectResponse($this->router->generate('access_denied')));
        }

    }
}
?>
