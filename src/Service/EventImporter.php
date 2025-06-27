<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Venue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventImporter
{
    private HttpClientInterface $client;
    private EntityManagerInterface $em;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $em, string $apiKey)
    {
        $this->client = $client;
        $this->em = $em;
        $this->apiKey = $apiKey;
    }

    public function importEvents(int $size = 100): void
    {
        //for the 3 event Categories
        // $categories = [
        //     'KZFzniwnSyZfZ7v7nJ', //concerts
        //     'KZFzniwnSyZfZ7v7nE', //sports
        //     'KZFzniwnSyZfZ7v7na' //Arts &Theatre
        // ];

        $response = $this->client->request('GET', 'https://app.ticketmaster.com/discovery/v2/events', [
            'query' => [
                'apikey' => $this->apiKey,
                'size' => $size,
                //'segmentId' => $segmentId
            ]
        ]);
            
        $data = $response->toArray();
            // dump($data); die; // for debugging

        if (!isset($data['_embedded']['events'])) {
            echo "No events found.\n";
            return;
        }

        foreach ($data['_embedded']['events'] as $item) {
        // Handle venue as an object
            $venueData = $item['_embedded']['venues'][0] ?? null;

            $event = new Event();
            $event->setName($item['name']);
            $event->setDescription($item['info'] ?? 'No description available');
            $event->setImage($item['image'] ?? null);

            $event->setOrganiser($item['_embedded']['attractions'][0]['name'] ?? 'Unknown Organiser');

            $event->setCapacity($item['_embedded']['venues'][0]['capacity'] ?? 0);
            $event->setPurchaseStartDate(new \DateTime($item['dates']['start']['dateTime'] ?? 'now'));
            $event->setPurchaseEndDate(new \DateTime($item['dates']['end']['dateTime'] ?? 'now'));

           
            //$externalVenueId = $venueData['id'] ?? null;
            // if (!$externalVenueId) {
            //     continue; // skip if no unique ID
            // }

            if($venueData){
                $venue = new Venue();
                $venue->setName($venueData['name'] ?? 'Unknown');
                $venue->setAddress($venueData['address']['line1'] ?? 'Address not available');
                $venue->setCapacity($venueData['capacity'] ?? 0);
                $this->em->persist($venue);
            } else {
                $venue = $this->em->getRepository(Venue::class)->findOneBy(['name' => 'Default Venue']);
                if (!$venue) {
                    $venue = new Venue();
                    $venue->setName('Default Venue');
                    $venue->setAddress('Unknown Venue');
                    $venue->setCapacity(0);
                    $this->em->persist($venue);
                }
            }

            $event->setVenue($venue);

                // // Try to find the venue by external ID
                // $venue = $this->em->getRepository(Venue::class)->findOneBy([
                //     'externalVenueId' => $externalVenueId,
                // ]);
                // if (!$venue) {
                //     $venue = new Venue();
                //     $venue->setExternalVenueId($externalVenueId);
                //     $venue->setName($venueData['name'] ?? 'Unknown');
                //     $venue->setAddress($venueData['address']['line1'] ?? 'Address not available');
                //     $venue->setCapacity($venueData['capacity'] ?? 0);
                //     $this->em->persist($venue);
                // }
                
            
                // Handle classification/category
                // $classification = $item['classifications'][0] ?? null;
                // if ($classification && isset($classification['segment']['name'])) {
                //     $categoryName =  $classification['segment']['name'];
                //     $category = $this->em->getRepository(Event)
                //     $event->setCategory($classification['segment']['name'] ?? 'Unknown');
                //     //$event->setGenre($classification['genre']['name'] ?? null);
                //     //$event->setSubGenre($classification['subGenre']['name'] ?? null);
                // } else {
                //     $event->setCategory(null);
                // }

                echo "Importing: " . $item['name'] . PHP_EOL;

                $this->em->persist($event);
            }
        
        $this->em->flush();
    }
}

