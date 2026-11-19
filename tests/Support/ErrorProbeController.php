<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test-only routes (loaded in the test environment only) that produce errors no real endpoint can yet.
 */
final class ErrorProbeController extends AbstractController
{
    #[Route('/api/_probe/forbidden', methods: ['GET'])]
    public function forbidden(): Response
    {
        throw $this->createAccessDeniedException('secret internals');
    }

    #[Route('/api/_probe/boom', methods: ['GET'])]
    public function boom(): Response
    {
        throw new \RuntimeException('secret internals');
    }
}
