<?php

namespace App\Controller;

use App\Entity\Command;
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

#[Route('/api/commands')]
class CommandController extends AbstractController
{
    #[Route('/', name: 'app_command')]
    public function index(CommandRepository $commandRepository, SerializerInterface $serializer): JsonResponse
    {
        $commands = $commandRepository->findAll();
        $jsonCommandList = $serializer->serialize($commands, 'json', ['groups' => 'getCommands']);

        return new JsonResponse($jsonCommandList, Response::HTTP_OK, [], true);
    }

    #[Route('/create', name: 'app_create_command')]
    public function createCommand(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
                               UrlGeneratorInterface $urlGenerator, UserRepository $userRepository): JsonResponse
    {
        $command = $serializer->deserialize($request->getContent(), Command::class, 'json');

        $content = $request->toArray();
        $users = $content['user'] ?? [];

        foreach ($users as $userData) {
            $idUser = $userData['idUser'] ?? - 1;
            $command->addUser($userRepository->find($idUser));
        }

        $em->persist($command);
        $em->flush();

        $jsonCommand = $serializer->serialize($command, 'json', ['groups' => 'getCommands']);
        $location = $urlGenerator->generate('app_command_id', ['id' => $command->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonCommand, Response::HTTP_CREATED, ['location' => $location], true);
    }

    #[Route('/update/{id}', name:'app_update_command', methods:['PUT'])]
    public function updateCommand(Request $request, SerializerInterface $serializer, Command $currentCommand, EntityManagerInterface $em,
                               UserRepository $userRepository): JsonResponse
    {
        $updateCommand = $serializer->deserialize($request->getContent(),
            Command::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentCommand]);

        $content = $request->toArray();
        $users = $content['user'] ?? [];

        foreach ($users as $userData) {
            $idUser = $userData['idUser'] ?? - 1;
            $updateCommand->addUser($userRepository->find($idUser));
        }

        $em->persist($updateCommand);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/delete/{id}', name: 'app_delete_command_id',  requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteCommand(Command $command, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($command); // Régler l'erreur qui survient lorsque des utilisateurs possèdent la commande en question
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'app_command_id', methods: ['GET'])]
    public function getCommandDetail(int $id, SerializerInterface $serializer, CommandRepository $commandRepository): JsonResponse
    {

        $command = $commandRepository->find($id);
        if ($command) {
            $jsonCommand = $serializer->serialize($command, 'json', ['groups' => 'getCommands']);
            return new JsonResponse($jsonCommand, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
