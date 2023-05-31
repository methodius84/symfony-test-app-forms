<?php

namespace App\Controller;

use App\Entity\SomeEntity;
use App\Form\CreateSomeEntityFormType;
use App\Repository\SomeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\DateTime;

class FormController extends AbstractController
{
    private $em;
    private SomeEntityRepository $entityRepository;

    public function __construct(EntityManagerInterface $entityManager, SomeEntityRepository $someEntityRepository)
    {
        $this->em = $entityManager;
        $this->entityRepository = $someEntityRepository;
    }

    #[Route('/some/create', name: 'create_entity', methods: ['GET', 'POST'])]
    public function createProduct(Request $request): Response
    {
        $entity = new SomeEntity();
        $form = $this->createForm(CreateSomeEntityFormType::class, $entity);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $entity->setCreatedAt(new \DateTime());
            $this->em->persist($entity);
            $this->em->flush();
            return $this->render('forms/create.html.twig', [
                'form' => $form->createView(),
            ]);
        }
        return $this->render('forms/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/some/update/{id}', name: 'update_entity',methods: ['GET', 'POST'])]
    public function updateEntity(int $id, Request $request): Response
    {

        $entity = $this->entityRepository->find($id);
        if($entity === null){
            return new Response('There is no such entity', 404);
        }

        $form = $this->createForm(CreateSomeEntityFormType::class, $entity);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
            $entity->setUpdatedAt(new \DateTime());
            if ($entity->getIsActive()){
                // TODO: search for id to update timestamp
                $file = fopen($this->getParameter('kernel.project_dir').'/entities.txt', 'a+');
                fwrite($file, $entity->getId().';'.$entity->getName().';'.$entity->getUpdatedAt()->format('Y-m-d H:i:s').PHP_EOL);
                fclose($file);
            }
            else{
                $this->clearLog($entity);
            }

            $this->em->persist($entity);
            $this->em->flush();
        }
        return $this->render('some/edit.html.twig', [
            'form' => $form,
            'entity' => $entity,
        ]);
    }

    #[Route('/some/delete/{id}', name: 'delete_entity', methods: 'GET')]
    public function deleteEntity(int $id): Response
    {
        $entity = $this->entityRepository->find($id);
        $this->clearLog($entity);
        $this->em->remove($entity);
        $this->em->flush();
        return new Response('Deleted successfully', 200);
    }

    private function clearLog(SomeEntity $entity): void
    {
        $lines = file($this->getParameter('kernel.project_dir').'/entities.txt', FILE_IGNORE_NEW_LINES);
        foreach ($lines as $key => $line){
            $array = explode(';', $line);
            if ((int)$array[0] === $entity->getId()){
                unset($lines[$key]);
            }
        }
        file_put_contents($this->getParameter('kernel.project_dir').'/entities.txt', implode(PHP_EOL, $lines));
    }
}
