<?php

namespace App\Controller;

use App\Entity\Device;
use App\Entity\Port;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DeviceController extends AbstractController
{
    /**
     * @Route("/devices/", name="devices")
     */
    public function index()
    {
        $devices = $this->getDoctrine()->getRepository(Device::class)->findBy(array(), array('type' => 'ASC'));

        return $this->render("devices/index.html.twig", [
            "devices" => $devices,
        ]);
    }

    /**
     * @Route("/devices/create", name="devices_create")
     */
    public function create(Request $request): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $device = new Device();
        $messages = array();

        $form = $this->createFormBuilder($device)
            ->add("type", TextType::class)
            ->add("ports", TextType::class)
            ->add("submit", SubmitType::class, ['label' => "Create device"])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $db_device = $this->getDoctrine()->getRepository(Device::class)->findOneBy(array("type" => $device->getType()));
            $input_ports = explode(";", $device->getPorts());
            sort($input_ports);
            
            if ($db_device) {
                array_push($messages, "Device ".$device->getType()." already exist - updating if necessary");

                $db_device_ports = explode(";", $db_device->getPorts());

                foreach ($input_ports as $port) {
                    if (!in_array($port, $db_device_ports)) {
                        $db_port = $this->getDoctrine()->getRepository(Port::class)->findOneBy(array("number" => $port));

                        if ($db_port) {
                            array_push($messages, "Port ".$port." already exist - updating if necessary");
                            $db_port_devices = explode(";", $db_port->getDevices());
                            if (!in_array($device->getType(), $db_port_devices)) {
                                array_push($messages, "Updating port ".$port);
                                array_push($db_port_devices, $device->getType());
                                sort($db_port_devices);
                                $db_port->setDevices(join(";", $db_port_devices));
                            }
                        } else {
                            array_push($messages, "Creating port ".$port);

                            $new_port = new Port();
                            $new_port->setNumber($port);
                            $new_port->setDevices($device->getType());
                            $entityManager->persist($new_port);
                        }

                        $entityManager->flush();

                        array_push($db_device_ports, $port);
                    }
                }

                sort($db_device_ports);
                $db_device->setPorts(join(";", $db_device_ports));
                $entityManager->flush();
            } else {
                array_push($messages, "Creating device ".$device->getType());

                foreach ($input_ports as $port) {
                    $db_port = $this->getDoctrine()->getRepository(Port::class)->findOneBy(array("number" => $port));

                    if ($db_port) {
                        array_push($messages, "Port ".$port." already exist - updating if necessary");
                        $db_port_devices = explode(";", $db_port->getDevices());
                        if (!in_array($device->getType(), $db_port_devices)) {
                            array_push($messages, "Updating port ".$port);
                            array_push($db_port_devices, $device->getType());
                            sort($db_port_devices);
                            $db_port->setDevices(join(";", $db_port_devices));
                        }
                    } else {
                        array_push($messages, "Creating port ".$port);

                        $new_port = new Port();
                        $new_port->setNumber($port);
                        $new_port->setDevices($device->getType());
                        $entityManager->persist($new_port);
                    }

                    $entityManager->flush();
                }

                $entityManager->persist($device);
                $entityManager->flush();
            }
        }

        return $this->render("devices/create.html.twig", [
            "form" => $form->createView(),
            "messages" => $messages,
        ]);
    }
}