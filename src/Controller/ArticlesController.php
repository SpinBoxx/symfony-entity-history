<?php

namespace App\Controller;



use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ArticlesController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em){
        $this->em = $em;
    }

    #[Route(path: '/', name: 'articles', methods: ['GET'])]
    public function index(): Response
    {
        $articles = $this->em->getRepository(Article::class)->findAll();

        return $this->render("articles/index.html.twig", [
            "articles" => $articles
        ]);
    }

    #[Route(path: '/article/{articleId}/edit', name: 'articles_edit', methods: ['GET'])]
    public function editArticle($articleId): Response
    {
        $article = $this->em->getRepository(Article::class)->find($articleId);

        if($article){
            return $this->render("articles/edit.html.twig", [
                "article" => $article
            ]);
        }
        return $this->redirectToRoute("articles");
    }

    #[Route(path: '/articles/add', name: 'articles_add', methods: ['POST'])]
    public function addArticle(Request $request, ValidatorInterface $validatorInterface): Response
    {
        $params = $request->request->all();
        $newArticle = new Article();
        $newArticle->setPrice($params['price']);
        $newArticle->setTitle($params['title']);
     
        $errors = $validatorInterface->validate($newArticle);

        if(count($errors) > 0){
            $errorsString = (string) $errors;
            return new Response($errorsString);
        }
        $this->em->persist($newArticle);
        $this->em->flush();
        return $this->redirectToRoute("articles");
    }

    #[Route(path: '/articles/{articleId}/update', name: 'articles_update', methods: ['POST'])]
    public function updateArticle(Request $request, ValidatorInterface $validatorInterface, $articleId): Response
    {
        $article = $this->em->getRepository(Article::class)->find($articleId);
      
        if($article){
            $params = $request->request->all();

            $article->setPrice($params['price']);
            $article->setTitle($params['title']);
    
            $errors = $validatorInterface->validate($article);
            if(count($errors) > 0){
                $errorsString = (string) $errors;
                return new Response($errorsString);
            }
        
            $this->em->flush();  
        }
        return $this->redirectToRoute("articles");
    }

    #[Route(path: '/articles/{articleId}/delete', name: 'articles_delete', methods: ['GET'])]
    public function deleteArticle($articleId, ValidatorInterface $validatorInterface): Response
    {
        $article = $this->em->getRepository(Article::class)->find($articleId);
        if($article){
            $this->em->remove($article);
            $this->em->flush();
        }
        
        return $this->redirectToRoute("articles");
    }
}
