<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class JwtUserSubscriber implements EventSubscriberInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    # Checks for the 'jwt_user' attribute in the request and injects jwt_user into Twig globals
    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('jwt_user')) {
            $this->twig->addGlobal('jwt_user', $request->attributes->get('jwt_user'));
        }
    }

    # Listens for the CONTROLLER event
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
?>
