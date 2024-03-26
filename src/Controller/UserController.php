<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\ExceptionSubscriber;
use App\Repository\CommandRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user')]
    public function index(UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $users = $userRepository->findAll();
        $jsonUserList = $serializer->serialize($users, 'json', ['groups' => 'getUsers']);

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    #[Route('/create', name: 'app_create_user')]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
                               UrlGeneratorInterface $urlGenerator, CommandRepository $commandRepository): JsonResponse
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $content = $request->toArray();
        $idCommand = $content['idCommand'] ?? - 1;

        $user->setCommand($commandRepository->find($idCommand));

        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
        $location = $urlGenerator->generate('app_user_id', ['id' => $user->getId()]
            , UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['location' => $location], true);
    }

    #[Route('/update/{id}', name:'app_update_user', methods:['PUT'])]
    public function updateBook(Request $request, SerializerInterface $serializer
        , User $currentUser, EntityManagerInterface $em, CommandRepository $commandRepository): JsonResponse
    {
        $updateUser = $serializer->deserialize($request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]);
        $content = $request->toArray();
        $idCommand = $content['$idCommand'] ?? -1;
        $updateUser->setCommand($commandRepository->find($idCommand));

        $em->persist($updateUser);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/delete/{id}', name: 'app_delete_user_id',  requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteUser(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'app_user_id',  requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getUserDetail(User $user, SerializerInterface $serializer
        , UserRepository $userRepository): JsonResponse
    {
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
