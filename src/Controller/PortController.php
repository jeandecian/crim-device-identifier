<?php

namespace App\Controller;

use App\Entity\Device;
use App\Entity\Port;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PortController extends AbstractController
{
    /**
     * @Route("/ports/", name="ports")
     */
    public function index()
    {
        $ports = $this->getDoctrine()->getRepository(Port::class)->findBy(array(), array('number' => 'ASC'));

        return $this->render("ports/index.html.twig", [
            "ports" => $ports,
        ]);
    }

    /**
     * @Route("/ports/create", name="ports_create")
     */
    public function create(Request $request) : Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $port = new Port();
        $messages = array();

        $form = $this->createFormBuilder($port)
            ->add("number", IntegerType::class, array("attr" => array("min" => 0, "max" => 65536)))
            ->add("devices", TextType::class)
            ->add("submit", SubmitType::class, ['label' => "Create port"])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $db_port = $this->getDoctrine()->getRepository(Port::class)->findOneBy(array("number" => $port->getNumber()));
            $input_devices = explode(";", $port->getDevices());
            sort($input_devices);
            
            if ($db_port) {
                array_push($messages, "Port ".$port->getNumber()." already exist - updating if necessary");

                $db_port_devices = explode(";", $db_port->getDevices());

                foreach ($input_devices as $device) {
                    if (!in_array($device, $db_port_devices)) {
                        $db_device = $this->getDoctrine()->getRepository(Device::class)->findOneBy(array("type" => $device));

                        if ($db_device) {
                            array_push($messages, "Device ".$device." already exist - updating if necessary");
                            $db_device_ports = explode(";", $db_device->getPorts());
                            if (!in_array($port->getNumber(), $db_device_ports)) {
                                array_push($messages, "Updating device ".$device);
                                array_push($db_device_ports, $port->getNumber());
                                sort($db_device_ports);
                                $db_device->setPorts(join(";", $db_device_ports));
                            }
                        } else {
                            array_push($messages, "Creating device ".$device);

                            $new_device = new Device();
                            $new_device->setType($device);
                            $new_device->setDevices($port->getNumber());
                            $entityManager->persist($new_device);
                        }

                        $entityManager->flush();

                        array_push($db_port_devices, $device);
                    }
                }

                sort($db_port_devices);
                $db_port->setDevices(join(";", $db_port_devices));
                $entityManager->flush();
            } else {
                array_push($messages, "Creating port ".$port->getNumber());

                foreach ($input_devices as $device) {
                    if (!in_array($device, $db_port_devices)) {
                        $db_device = $this->getDoctrine()->getRepository(Device::class)->findOneBy(array("type" => $device));

                        if ($db_device) {
                            array_push($messages, "Device ".$device." already exist - updating if necessary");
                            $db_device_ports = explode(";", $db_device->getPorts());
                            if (!in_array($port->getNumber(), $db_device_ports)) {
                                array_push($messages, "Updating device ".$device);
                                array_push($db_device_ports, $port->getNumber());
                                sort($db_device_ports);
                                $db_device->setPorts(join(";", $db_device_ports));
                            }
                        } else {
                            array_push($messages, "Creating device ".$device);

                            $new_device = new Device();
                            $new_device->setType($device);
                            $new_device->setDevices($port->getNumber());
                            $entityManager->persist($new_device);
                        }

                        $entityManager->flush();

                        array_push($db_port_devices, $device);
                    }
                }

                $entityManager->persist($port);
                $entityManager->flush();
            }
        }

        return $this->render("ports/create.html.twig", [
            "form" => $form->createView(),
            "messages" => $messages,
        ]);
    }
}