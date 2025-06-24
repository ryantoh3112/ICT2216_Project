<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\EventCategoryRepository;

#[Route('/categories', name: 'category_')]
final class CategoryController extends AbstractController
{
    #[Route('', name: 'list')]
    public function index(EventCategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('event/categories.html.twig', [
            'categories' => $categories,
        ]);
    }
}