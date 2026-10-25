<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Security;

use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces `#[RequiresPermission]` once the arguments are resolved, so the order is 401 (firewall) -> 404
 * (CurrentOrganizerResolver) -> 403 (this listener, via the voters).
 */
#[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS)]
final readonly class RequiresPermissionListener
{
    public function __construct(private AuthorizationCheckerInterface $authorization) {}

    public function __invoke(ControllerArgumentsEvent $event): void
    {
        /** @var list<RequiresPermission> $requirements */
        $requirements = $event->getAttributes(RequiresPermission::class);
        if ([] === $requirements) {
            return;
        }

        $arguments = $this->namedArguments($event);

        foreach ($requirements as $requirement) {
            $subject = null !== $requirement->subject
                ? ($arguments[$requirement->subject] ?? throw new \LogicException(\sprintf('#[RequiresPermission] names the unknown argument "$%s".', $requirement->subject)))
                : $this->organizerArgument($arguments);

            if (!$this->authorization->isGranted($requirement->permission->value, $subject)) {
                throw new AccessDeniedException(\sprintf('Missing permission "%s".', $requirement->permission->value));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function namedArguments(ControllerArgumentsEvent $event): array
    {
        $names = [];
        $controller = $event->getController();
        if (\is_array($controller)) {
            $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif ($controller instanceof \Closure) {
            $reflection = new \ReflectionFunction($controller);
        } elseif (\is_object($controller)) {
            $reflection = new \ReflectionMethod($controller, '__invoke');
        } else {
            return [];
        }

        foreach ($reflection->getParameters() as $index => $parameter) {
            $names[$parameter->getName()] = $event->getArguments()[$index] ?? null;
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function organizerArgument(array $arguments): ?OrganizerView
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof OrganizerView) {
                return $argument;
            }
        }

        return null;
    }
}
