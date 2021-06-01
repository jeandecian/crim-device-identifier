<?php

namespace App\Controller;

use App\Entity\Device;
use App\Entity\Port;
use App\Entity\Scan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ScannerController extends AbstractController
{
    /**
     * @Route("/scanner/", name="scanner")
     */
    public function index(Request $request): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $scan = new Scan();

        $form = $this->createFormBuilder($scan)
            ->add("host", TextType::class)
            ->add("device", HiddenType::class, ['data' => ""])
            ->add("probability", HiddenType::class, ['data' => 0.0])
            ->add("date", HiddenType::class, ['data' => ""])
            ->add("submit", SubmitType::class, ['label' => "Scan"])
            ->getForm();

        $form->handleRequest($request);

        $matches = array();
        $results = array();
        $open_ports = array();
        $closed_ports = array();
        $message = "";

        if ($form->isSubmitted() && $form->isValid()) {
            $db_scan = $this->getDoctrine()->getRepository(Scan::class)->findOneBy(array("host" => $scan->getHost()));
            $db_devices = $this->getDoctrine()->getRepository(Device::class)->findAll();
            $db_ports = $this->getDoctrine()->getRepository(Port::class)->findAll();

            foreach ($db_devices as $device) {
                array_push($matches, array(
                    "device" => $device->getType(),
                    "probability" => 0.0
                ));
            }

            foreach ($db_ports as $port) {
                $fp = @fsockopen($scan->getHost(), $port->getNumber(), $errno, $errstr, 0.4);
                $sname = getservbyport($port->getNumber(), "tcp");

                if (is_resource($fp) && $sname) {
                    $db_match_port = $this->getDoctrine()->getRepository(Port::class)->findOneBy(array("number" => $port->getNumber()));
                    $matches_devices = explode(";", $db_match_port->getDevices());

                    foreach ($matches_devices as $match) {
                        $index = array_search($match, array_column($matches, "device"));
                        $matches[$index]["probability"] += round(1.0 / sizeof($matches_devices), 2);
                    }

                    array_push($open_ports, $port->getNumber());
                    array_push($results, array($port->getNumber(), $sname, $matches_devices));
                }
            }

            if (sizeof($results)) {
                usort($results, function ($a, $b) {
                    return $a[0] <=> $b[0];
                });

                $matches_count = sizeof($results);
                usort($matches, function ($a, $b) {
                    return $b["probability"] <=> $a["probability"];
                });

                $result = [];
                foreach ($matches as $match) {
                    $probability = round($match["probability"] * 100 / $matches_count, 1);
                    if ($probability > 0) {
                        array_push($result, array(
                            "device" => $match["device"],
                            "probability" => $probability
                        ));
                    }
                }

                if ($db_scan) {
                    $db_scan->setDevice($result[0]["device"]);
                    $db_scan->setProbability($result[0]["probability"]);
                    $db_scan->setDate(date("Y-m-d"));
                } else {
                    $scan->setDevice($result[0]["device"]);
                    $scan->setProbability($result[0]["probability"]);
                    $scan->setDate(date("Y-m-d"));

                    $entityManager->persist($scan);
                }

                $entityManager->flush();

                $matches = $result;
                $closed_ports = array_diff(
                    explode(
                        ";",
                        $this->getDoctrine()->getRepository(Device::class)->findOneBy(
                            array("type" => $result[0]["device"])
                        )->getPorts()
                    ),
                    $open_ports
                );
            } else {
                $message = "No results";
            }
        }

        return $this->render("scanner/index.html.twig", [
            "form" => $form->createView(),
            "host" => $scan->getHost(),
            "matches" => $matches,
            "results" => $results,
            "open_ports" => $open_ports,
            "closed_ports" => $closed_ports,
            "message" => $message
        ]);
    }
}
