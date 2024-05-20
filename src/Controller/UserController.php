<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\ExceptionSubscriber;
use App\Repository\CommandRepository;
use App\Repository\UserRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/api/books', name: 'books', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Retourne la liste des utilisateurs',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class, groups: ['getUsers']))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'La page que lon veut récupérer',
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Le nombre déléments que lon veut récupérer',
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Tag(name: 'Users')]
    #[Security(name: 'Bearer')]
    #[Route('/', name: 'app_user', methods: ['GET'])]
    public function index(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllUsers-" . $page . "-" . $limit;

        $jsonUserList = $cache->get($idCache,
            function(ItemInterface $item) use ($serializer, $userRepository, $page, $limit) {
            //echo ("Cet élément n'est pas encore en cache !\n");
            $item->tag('usersCache');
            // $item->expiresAfter(60); Signifie que le cache sera supprimé après 60 secondes.

            $userList = $userRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getUsers"]);

            return $serializer->serialize($userList, 'json', $context);
        });

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    #[Route('/create', name: 'app_create_user', methods: ['POST'])]
    #[isGranted('ROLE_ADMIN', message: 'Vous ne disposez pas des privilèges suffisants pour créer un utilisateur')]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
                               UrlGeneratorInterface $urlGenerator, CommandRepository $commandRepository
                                , ValidatorInterface $validator): JsonResponse
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

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

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/update/{id}', name:'app_update_user', methods:['PUT'])]
    #[isGranted('ROLE_ADMIN', message: 'Vous ne disposez pas des privilèges suffisants pour modifier un utilisateur')]
    public function updateBook(Request $request, SerializerInterface $serializer, User $currentUser, EntityManagerInterface $em
        , CommandRepository $commandRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newUser = $serializer->deserialize($request->getContent(), User::class, 'json');

        $currentUser->setEmail($newUser->getEmail());
        $currentUser->setUsername($newUser->getUsername());
        $currentUser->setGender($newUser->getGender());

        $errors = $validator->validate($currentUser);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idCommand = $content['idCommand'] ?? -1;

        $currentUser->setCommand($commandRepository->find($idCommand));

        $em->persist($currentUser);
        $em->flush();

        $cache->invalidateTags(["usersCache"]);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/delete/{id}', name: 'app_delete_user_id',  requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[isGranted('ROLE_ADMIN', message: 'Vous ne disposez pas des privilèges suffisants pour supprimer un utilisateur')]
    public function deleteUser(User $user, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        $cache->invalidateTags(["usersCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'app_user_id',  requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getUserDetail(User $user, SerializerInterface $serializer
        , UserRepository $userRepository, VersioningService $versioningService): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getUsers"]);
        $context->setVersion($versioningService->getVersion());

        $jsonUser = $serializer->serialize($user, 'json', $context);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
