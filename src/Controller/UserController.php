<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ParameterBag;


class UserController extends AbstractController
{
    /**
     * @Route("/api/users", methods={"GET"},name="users")
     */
    public function index(): Response
    {
        $user = null;
        $user = $this->getData();
        return $this->json(['users' =>  $user]);
    }
    /**
     * 
     * @Route("/api/getuser/{id}", methods={"GET"} ,name="getuser")
     */
    public function findUser($id)
    {
        $users = $this->searchUser($id);
        return $this->json([
            'user' => $users,
            'path' => 'this is test',
        ]);
    }
    /**
     * 
     * @Route("/api/new", methods={"POST"} ,name="new")
     */
    public function createUser(Request $request)
    {
        $requestData = json_decode($request->getContent(), true);

        if (!isset($requestData['email']) || !$this->valid_email($requestData['email']) || !isset($requestData['name'])) {
            return $this->returnResponse(400, $requestData);
        }

        $checkUser = $this->searchUser(null, $requestData['email']);

        if (isset($checkUser)) {
            return $this->returnResponse(409, $checkUser);
        }

        $userlist = $this->getData()['users'];
        $lastUserId = max(array_column($userlist, 'id')) + 1;
        $data = @simplexml_load_file($this->getParameter('data_dir') . '/users.xml');

        $character = $data->addChild('users');
        $character->addChild('id', $lastUserId);
        $character->addChild('name', $requestData['name']);
        $character->addChild('email', $requestData['email']);
        file_put_contents($this->getParameter('data_dir') . '/users.xml', $data->asXML());

        return $this->returnResponse(200, $this->getData()['users']);
    }


    /**
     * 
     * @Route("/api/delete/{id}", methods={"DELETE"} ,name="delete")
     */
    public function delete($id)
    {
        $data = @simplexml_load_file($this->getParameter('data_dir') . '/users.xml');
        $index = 0;
        $i = 0;
        foreach ($data->users as $user) {
            if ($user->id == $id) {
                $index = $i;
                break;
            }
            $i++;
        }
        unset($data->users[$index]);
        file_put_contents($this->getParameter('data_dir') . '/users.xml', $data->asXML());
        return $this->json(['statusCode' => 200, 'statusText' => 'User Deleted Succesfully '], 200);
    }
    
    /**
     * 
     * @Route("/api/edit/{id}", methods={"PUT"} ,name="edit")
     */
    public function edit(Request $request, $id)
    {

        $data = @simplexml_load_file($this->getParameter('data_dir') . '/users.xml');
        $requestData = json_decode($request->getContent(), true);
        $requestEmail = (isset($requestData['email'])) ? $this->valid_email($requestData['email']): null;
        $users = $this->searchUser($id);
        $i = 0;

        if (!$users) {
            return $this->returnResponse(404);
        }

        foreach ($data->users as $user) {
            if ($user->id == $id) {
                $user->email = ($requestEmail) ? $requestData['email'] : $user->email;
                $user->name = $requestData['name'] ? $requestData['name'] : $user->name;
                break;
            }
            $i++;
        }

       file_put_contents($this->getParameter('data_dir') . '/users.xml', $data->asXML()); 

        return $this->returnResponse(200, $this->searchUser($id));
    }

    private function getData()
    {
        if (file_exists($this->getParameter('data_dir') . '/users.xml')) {
            $data = @simplexml_load_file($this->getParameter('data_dir') . '/users.xml');
            return json_decode(json_encode($data), TRUE);
        }
        return;
    }

    private function searchUser($id = [], $email = [])
    {
        $data = $this->getData()['users'];
        $keysEmail = array_keys(array_column($data, 'email'), $email);
        $keys = array_keys(array_column($data, 'id'), $id);

        if (isset($id) && isset($keys)) {

            $users = array_map(function ($k) use ($data) {
                return $data[$k];
            }, $keys);
            return $users;
        }

        if (isset($email) && isset($keysEmail)) {

            $users = array_map(function ($k) use ($data) {
                return $data[$k];
            }, $keysEmail);
            return $users;
        }

        return null;
    }

    private function valid_email($str)
    {
        return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str)) ? FALSE : TRUE;
    }

    private function returnResponse($code, $data = [])
    {
        switch ($code) {
            case 200:
                $success['statusCode'] = 200;
                $success['result'] = ['data' => $data];
                return $this->json($success, 200);
                break;
            case 400:
                return $this->json(['statusCode' => 400, 'statusText' => 'Bad request Please check input data', 'requestBody' => $data], 400);
                break;
            case 404:
                return $this->json(['statusCode' => 404, 'statusText' => 'User not found', 'requestBody' => $data], 404);
                break;
            case 409:
                return $this->json(['statusCode' => 409, 'statusText' => 'User Alrady exsist', 'data' => $data], 409);
                break;
            default:
                return $this->json(['statusCode' => $code, 'statusText' => $data], $code);
        }
    }
}
