<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\Validator\Constraints as Assert;
class GameController extends AbstractController
{
    #[Route('/games', name: 'get_list_of_games', methods:['GET'])]
    public function getListOfGames(EntityManagerInterface $entityManager): JsonResponse
    {
        $data = $entityManager->getRepository(Game::class)->findAll();
        return $this->json(
            $data,
            headers: ['Content-Type' => 'application/json;charset=UTF-8']
        );
    }

    #[Route('/games', name: 'create_game', methods:['POST'])]
    public function createGame(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if($currentUserId === null) {
			return new JsonResponse('User not found', 401);
		}

		if(ctype_digit($currentUserId) === false){
			return new JsonResponse('User not found', 401);
		}

		$currentUser = $entityManager->getRepository(User::class)->find($currentUserId);

		if($currentUser === null){
			return new JsonResponse('User not found', 401);
		}

		$newGame = new Game();
		$newGame->setState('pending');
		$newGame->setPlayerLeft($currentUser);

		$entityManager->persist($newGame);

		$entityManager->flush();

		return $this->json(
				$newGame,
				201,
				headers: ['Content-Type' => 'application/json;charset=UTF-8']
		);
    }


	#[Route('/game/{gameId}', name: 'fetch_game', methods:['GET'])]
	public function fetchGame(EntityManagerInterface $entityManager, $gameId): JsonResponse
	{
		if(!ctype_digit($gameId)){
			return new JsonResponse('Game not found', 404);
		}

		$game = $entityManager->getRepository(Game::class)->findOneBy(['id' => $gameId]);

		if($game === null){
			return new JsonResponse('Game not found', 404);
		}

		return $this->json(
				$game,
				headers: ['Content-Type' => 'application/json;charset=UTF-8']
		);
	}

    #[Route('/game/{gameId}/add/{playerRightId}', name: 'invite_right_user', methods:['PATCH'])]
    public function inviteRightUser(Request $request, EntityManagerInterface $entityManager, $gameId, $playerRightId): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if(empty($currentUserId)){
            return new JsonResponse('User not found', 401);
        }

		if(!ctype_digit($currentUserId)){
			return new JsonResponse('User not found', 401);
		}

		if(!ctype_digit($gameId)){
			return new JsonResponse('Game not found', 404);
		}

		if(!ctype_digit($playerRightId)){
			return new JsonResponse('User not found', 404);
		}


		$playerLeft = $entityManager->getRepository(User::class)->find($currentUserId);

		if($playerLeft === null){
			return new JsonResponse('User not found', 401);
		}

		$game = $entityManager->getRepository(Game::class)->find($gameId);

		if($game === null){
			return new JsonResponse('Game not found', 404);
		}

		if($game->getState() === 'ongoing' || $game->getState() === 'finished'){
			return new JsonResponse('Game already started', 409);
		}


		$playerRight = $entityManager->getRepository(User::class)->find($playerRightId);

		if($playerRight === null){
			return new JsonResponse('User not found', 404);
		}

		if($playerLeft->getId() === $playerRight->getId()){
			return new JsonResponse('You can\'t play against yourself', 409);
		}

		$game->setPlayerRight($playerRight);
		$game->setState('ongoing');

		$entityManager->flush();

		return $this->json(
				$game,
				headers: ['Content-Type' => 'application/json;charset=UTF-8']
		);
	}

    #[Route('/game/{gameId}', name: 'play', methods:['PATCH'])]
    public function play(Request $request, EntityManagerInterface $entityManager, $gameId): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if(ctype_digit($currentUserId) === false){
            return new JsonResponse('User not found', 401);
        }

        $currentUser = $entityManager->getRepository(User::class)->find($currentUserId);

        if($currentUser === null){
            return new JsonResponse('User not found', 401);
        }
    
        if(ctype_digit($gameId) === false){
            return new JsonResponse('Game not found', 404);
        }

        $game = $entityManager->getRepository(Game::class)->find($gameId);

        if($game === null){
            return new JsonResponse('Game not found', 404);
        }

		if($game->getPlayerLeft()->getId() !== $currentUser->getId() && $game->getPlayerRight()->getId() !== $currentUser->getId()){
			return new JsonResponse('You are not a player of this game', 403);
		}

		$userIsPlayerLeft = false;
		if($game->getPlayerLeft()->getId() === $currentUser->getId()){
			$userIsPlayerLeft = true;
		}

        if($game->getState() === 'finished' || $game->getState() === 'pending'){
            return new JsonResponse('Game not started', 409);
        }

        $form = $this->createFormBuilder()
            ->add('choice', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->getForm();

        $choice = json_decode($request->getContent(), true);

        $form->submit($choice);

		if(!$form->isValid()){
			return new JsonResponse('Invalid choice', 400);
		}

		$data = $form->getData();

		if($data['choice'] !== 'rock' && $data['choice'] !== 'paper' && $data['choice'] !== 'scissors'){
			return new JsonResponse('Invalid choice', 400);
		}

		if($userIsPlayerLeft){
			$game->setPlayLeft($data['choice']);
			$entityManager->flush();

			if($game->getPlayRight() !== null){

				switch($data['choice']){
					case 'rock':
						if($game->getPlayRight() === 'paper'){
							$game->setResult('winRight');
						}elseif($game->getPlayRight() === 'scissors'){
							$game->setResult('winLeft');
						}else{
							$game->setResult('draw');
						}
						break;
					case 'paper':
						if($game->getPlayRight() === 'scissors'){
							$game->setResult('winRight');
						}elseif($game->getPlayRight() === 'rock'){
							$game->setResult('winLeft');
						}else{
							$game->setResult('draw');
						}
						break;
					case 'scissors':
						if($game->getPlayRight() === 'rock'){
							$game->setResult('winRight');
						}elseif($game->getPlayRight() === 'paper'){
							$game->setResult('winLeft');
						}else{
							$game->setResult('draw');
						}
						break;
				}
				$game->setState('finished');
				$entityManager->flush();
			}

			return $this->json(
					$game,
					headers: ['Content-Type' => 'application/json;charset=UTF-8']
			);
		}
		$game->setPlayRight($data['choice']);
		$entityManager->flush();

		if($game->getPlayLeft() !== null){

			switch($data['choice']){
				case 'rock':
					if($game->getPlayLeft() === 'paper'){
						$game->setResult('winLeft');
					}elseif($game->getPlayLeft() === 'scissors'){
						$game->setResult('winRight');
					}else{
						$game->setResult('draw');
					}
					break;
				case 'paper':
					if($game->getPlayLeft() === 'scissors'){
						$game->setResult('winLeft');
					}elseif($game->getPlayLeft() === 'rock'){
						$game->setResult('winRight');
					}else{
						$game->setResult('draw');
					}
					break;
				case 'scissors':
					if($game->getPlayLeft() === 'rock'){
						$game->setResult('winLeft');
					}elseif($game->getPlayLeft() === 'paper'){
						$game->setResult('winRight');
					}else{
						$game->setResult('draw');
					}
					break;
			}

			$game->setState('finished');
			$entityManager->flush();
		}
		return $this->json(
				$game,
				headers: ['Content-Type' => 'application/json;charset=UTF-8']
		);
    }

    #[Route('/game/{id}', name: 'delete_game', methods:['DELETE'])]
    public function deleteGame(EntityManagerInterface $entityManager, Request $request, $id): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

		if(ctype_digit($currentUserId) !== true){
			return new JsonResponse('User not found', 401);
		}
		$player = $entityManager->getRepository(User::class)->find($currentUserId);

		if($player === null){
			return new JsonResponse('User not found', 401);
		}

		if(ctype_digit($id) === false){
			return new JsonResponse('Game not found', 404);
		}

		$game = $entityManager->getRepository(Game::class)->findOneBy(['id' => $id, 'playerLeft' => $player]);

		if(empty($game)){
			$game = $entityManager->getRepository(Game::class)->findOneBy(['id' => $id, 'playerRight' => $player]);
		}

		if(empty($game)){
			return new JsonResponse('Game not found', 403);
		}

		$entityManager->remove($game);
		$entityManager->flush();

		return new JsonResponse(null, 204);
    }
}
