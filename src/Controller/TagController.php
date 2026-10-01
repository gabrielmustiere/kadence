<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\TagInput;
use App\Entity\Tag;
use App\Enum\Type\TagCategory;
use App\Form\TagType;
use App\Repository\TagRepository;
use App\Service\TagManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tags')]
#[IsGranted('ROLE_DIRECTION')]
final class TagController extends AbstractController
{
    public function __construct(
        private readonly TagManager $tagManager,
    ) {
    }

    #[Route('', name: 'app_tag_index', methods: ['GET', 'POST'])]
    public function index(Request $request, TagRepository $tagRepository): Response
    {
        $input = new TagInput();
        $form = $this->createForm(TagType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $input->category ?? throw new \LogicException('A validated tag has a category.');
            $tag = $this->tagManager->create($category, $input->label ?? '');
            $this->addFlash('success', \sprintf('Le tag « %s » est ajouté : %s.', $tag->getLabel(), mb_strtolower($category->label())));

            return $this->redirectToRoute('app_tag_index');
        }

        $tags = $tagRepository->findAllByCategory();

        return $this->render('tag/index.html.twig', [
            'form' => $form,
            'categories' => array_map(
                static fn (TagCategory $category): array => ['category' => $category, 'tags' => $tags[$category->value]],
                TagCategory::cases(),
            ),
            'holders' => $tagRepository->countHoldersByTag(),
        ]);
    }

    #[Route('/{id}/renommer', name: 'app_tag_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Tag $tag): Response
    {
        $input = TagInput::fromTag($tag);
        $form = $this->createForm(TagType::class, $input, ['with_category' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tagManager->rename($tag, $input->label ?? '');
            $this->addFlash('success', \sprintf('Le tag est renommé « %s ».', $tag->getLabel()));

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('tag/edit.html.twig', ['form' => $form, 'tag' => $tag]);
    }

    #[Route('/{id}/supprimer', name: 'app_tag_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Tag $tag): Response
    {
        if (!$this->isCsrfTokenValid('tag-' . $tag->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = $tag->getLabel();
        $this->tagManager->delete($tag);
        $this->addFlash('success', \sprintf('Le tag « %s » est supprimé.', $label));

        return $this->redirectToRoute('app_tag_index');
    }
}
