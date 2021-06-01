<?php

namespace App\Controller;

use App\Entity\Device;
use App\Entity\Port;
use App\Entity\Scan;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index()
    {
        return $this->render("index.html.twig", [
            "title" => "Device Identifier"
        ]);
    }
}
