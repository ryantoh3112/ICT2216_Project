<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Auth;
use App\Entity\Event;
use App\Entity\EventCategory;
use App\Entity\Venue;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\TicketType;
use App\Entity\History;
use App\Entity\JWTBlacklist;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create User
        $user = new User();
        $user->setName('John Doe');
        $user->setRole('ROLE_USER');
        $user->setCreatedAt(new \DateTime());
        $manager->persist($user);

        // Create Auth
        $auth = new Auth();
        $auth->setUser($user);
        $auth->setEmail('john@example.com');
        $auth->setPassword(password_hash('password', PASSWORD_BCRYPT));
        $manager->persist($auth);

        // Create Event Categories
        $category1 = new EventCategory();
        $category1->setName('Concerts');
        $category1->setDescription('Live music, festivals, and headline tours.');
        $category1->setImage('images/categories/concerts.jpg');
        $manager->persist($category1);

        $category2 = new EventCategory();
        $category2->setName('Sports');
        $category2->setDescription('Matches, tournaments, and live sports events.');
        $category2->setImage('images/categories/sports.jpg');
        $manager->persist($category2);

        $category3 = new EventCategory();
        $category3->setName('Arts, Theatre & Comedy');
        $category3->setDescription('Stand-up comedy, plays, and artistic showcases.');
        $category3->setImage('images/categories/arts.jpg');
        $manager->persist($category3);

        // Create Venue
        $venue1 = new Venue();
        $venue1->setName('Esplanade Concert Hall')
        ->setAddress('1 Esplanade Dr, Singapore 038981')
        ->setCapacity(2000)
        ->setDescription
        ("The Concert Hall is Esplanade's crown jewel, which seats 1,630 and another 197 in the gallery. 
        Chosen by Hamburg-based building data company Emporis in 2014 as one of the 15 most beautiful concert halls in the world, 
        the hall boasts superb acoustics. It is one of only five such halls in the world with similar state-of-the-art features, 
        which include reverberation chambers and an acoustic canopy that adapts the hall for different musical performances, 
        producing optimum sound at every concert.
        ")
        ->setImage('images/venues/esplanade_concert_hall.jpg');

        $venue2 = new Venue();
        $venue2->setName('Singapore Indoor Stadium')
        ->setAddress('2 Stadium Walk, Singapore 397691')
        ->setCapacity(12000)
        ->setDescription
        ("The iconic Indoor Stadium has hosted a wide variety of sports and entertainment events, 
        including Western and Asian superstars, family entertainment, award shows, sports matches and other large-scale events. 
        It offers world-class technical facilities and a flexible configuration that scales for audiences from 4,000 to 
        12,000 spectators.
        ")
        ->setImage('images/venues/singapore_indoor_stadium.jpg');

        $venue3 = new Venue();
        $venue3->setName('Victoria Theatre')
        ->setAddress('11 Empress Pl, Singapore 179558')
        ->setCapacity(614)
        ->setDescription
        ("Constructed between 1855 and 1862 and originally built as a Town Hall, Victoria Theatre is the older of the two buildings 
        that make up this performing arts venue. The Town Hall was converted into a theatre in 1908 and was renamed Victoria Theatre.
        Victoria Theatre is set in a black box, offering a seating capacity of 614 and the flexibility for the formation of an 
        extended forestage or an orchestra pit when required. When the orchestra pit is in use, the seating capacity is 536. 
        The Theatre is wheelchair accessible in the Stalls area. The intimate space features a curved seating plan that “hugs” the 
        stage, bringing the audience closer to performers. Cast iron components were taken from the old Theatre’s chairs and 
        re-purposed into horizontal bands set across the timber walls to improve the acoustics of the space.
        Adjacent to the Theatre is a suite, which can be used as a private hospitality suite or as an extension of the Theatre Main 
        Foyer. Suitable for a maximum of 10 – 15 people, the Theatre Suite is linked to the Theatre via Door 3.
        ")
        ->setImage('images/venues/victoria_theatre.jpg');

        $venue4 = new Venue();
        $venue4->setName('Victoria Concert Hall')
        ->setAddress('11 Empress Pl, Singapore 179558')
        ->setCapacity(673)
        ->setDescription
        ("First established in 1862, the Victoria Theatre & Victoria Concert Hall is one of the most recognisable landmarks in 
        Singapore. The heritage building located in the heart of the city’s Civic District contains a 614-seat Theatre and 
        a 673-seat Concert Hall. In 2010, the national monument underwent a four-year refurbishment to restore its neo-classical 
        façade while getting new state-of-the-art facilities and amenities. The redevelopment also saw the addition of two smaller 
        rooms for music, dance and theatre rehearsals. Having played a role in the country’s history for over 150 years, 
        the Victoria Theatre & Victoria Concert Hall continues to be an exciting mid-sized platform, supporting the growth of 
        Singapore’s arts industry. Victoria Theatre & Victoria Concert Hall is managed by Arts House Ltd.
        ")
        ->setImage('images/venues/victoria_concert_hall.jpg');

        $venue5 = new Venue();
        $venue5->setName('Capitol Theatre')
        ->setAddress('17 Stamford Rd, Singapore 178907')
        ->setCapacity(977)
        ->setDescription
        ("The multi-functional space boasts one of the largest single screens in the region 
        and an advanced rotational floor system that can 
        transform the interior from a 1,000-seat configuration to a flat floor, 
        and multiple seating arrangements in between, in under eight minutes. 
        ")
        ->setImage('images/venues/capitol_theatre.jpeg');

        $venue6 = new Venue();
        $venue6->setName('The Star Theatre')
        ->setAddress('1 Vista Exchange Green, Singapore 138617')
        ->setCapacity(5000)
        ->setDescription
        ("The key venue of The Star Performing Arts Centre, The Star Theatre is fitted with high-end audio, video and production 
        lighting systems for an exceptional audio-visual experience. It heightens your enjoyment of a wide range of amplified music 
        and speech events, as well as large-scale musical theatre and dance performances.
        Featuring a traditional horseshoe shape, this auditorium offers custom-designed seating, including stalls and two circle 
        levels. The last row of seats at the upper circle level is just 56 metres from the stage, creating an intimate ambience for 
        this grand 5,000-seat venue.
        Theatre sightlines are maximised and acoustics are designed for complete audience enjoyment. Imagine the crisp clarity of 
        speeches in an auditorium supported by the audio system of studio recordings with ‘live’ broadcast quality.
        The excellent back-of-house facility will also pamper artistes and the technical crew. They will enjoy a comprehensive suite 
        of services on and off stage at the Reception Room and other dressing room facilities.
        ")
        ->setImage('images/venues/the_star_theatre.jpg');
        
        $venue7 = new Venue();
        $venue7->setName('Gateway Theatre')
        ->setAddress('3615 Jalan Bukit Merah, Singapore 159461')
        ->setCapacity(922)
        ->setDescription
        ("Gateway Theatre's two largest venues are the 922-seat main Theatre and the 207-seat Black Box. 
        Within the building are two studio spaces for multi-purpose use, an outdoor Sky Garden and small balcony gardens for 
        open-air performances. Originally used for  movie screenings in the 1980’s, the main Theatre is now the prime space and 
        heart of Gateway Theatre for arts and other performances. The intimate two-tiered theatre is fully equipped with a 
        12m x 6.75m LED wall and an array of visual, audio and lighting systems that can meet the needs of various art genres. 
        It’s also great for product launches, conferences, film screenings or lectures.
        ")
        ->setImage('images/venues/gateway_theatre.jpg');

        $venue8 = new Venue();
        $venue8->setName('Sands Grand Ballroom')
        ->setAddress('10 Bayfront Ave, Singapore 018956')
        ->setCapacity(8000)
        ->setDescription
        ("The Sands Ballroom on Level 5 is ideal for grand and luxurious events, 
        offering the flexibility to be divided into 16 rooms for simultaneous meetings. 
        When expanded, the Grand Ballroom extends to 7,672 sqm.
        ")
        ->setImage('images/venues/sands_grand_ballroom.jpg');

        $venue9 = new Venue();
        $venue9->setName('National Stadium')
        ->setAddress('1 Stadium Drive, Singapore 397629')
        ->setCapacity(55000)
        ->setDescription
        ("Opened in June 2014, the National Stadium is the pride of the nation providing spectators a stunning view of the 
        Singapore city skyline. The 55,000 capacity National Stadium has a retractable seating capability making it the only 
        stadium in the world able to host a multitude of events such as rugby, cricket, football, athletics, concerts, 
        family entertainment shows, national and community events.
        ")
        ->setImage('images/venues/national_stadium.jpg');

        $manager->persist($venue1);
        $manager->persist($venue2);
        $manager->persist($venue3);
        $manager->persist($venue4);
        $manager->persist($venue5);
        $manager->persist($venue6);
        $manager->persist($venue7);
        $manager->persist($venue8);
        $manager->persist($venue9);

        //Create Event
        $event1 = new Event();
        $event1->setVenue($venue9);
        $event1->setCategory($category1);
        $event1->setName('BLACKPINK Encore Tour');
        $event1->setCapacity(55000);
        $event1->setPurchaseStartDate(new \DateTime('2025-08-15'));
        $event1->setPurchaseEndDate(new \DateTime('2025-11-07'));
        $event1->setOrganiser('YG Entertainment');
        $event1->setDescription
        ("K-pop’s biggest girl group lights up the stage again with new choreography and fan-favourite songs.");
        $event1->setImage('images/events/blackpink.jpg');
        $manager->persist($event1);

        $event2 = new Event();
        $event2->setVenue($venue4);
        $event2->setCategory($category3);
        $event2->setName('Harry Potter: Visions of Magic');
        $event2->setCapacity(5000);
        $event2->setPurchaseStartDate(new \DateTime('2025-01-15'));
        $event2->setPurchaseEndDate(new \DateTime('2025-04-09'));
        $event2->setOrganiser('Neon and Warner Bros. Themed Entertainment');
        $event2->setDescription
        ("Step into the wizarding world in this immersive multimedia exhibition, featuring magical environments, iconic scenes, and spellbinding visual storytelling. 
        Explore the mysteries of the Ministry of Magic, the Room of Requirement, and more.");
        $event2->setImage('images/events/harry_potter.jpg');
        $manager->persist($event2);

        $event3 = new Event();
        $event3->setVenue($venue9);
        $event3->setCategory($category1);
        $event3->setName('Coldplay: Music of the Spheres Tour');
        $event3->setCapacity(50000);
        $event3->setPurchaseStartDate(new \DateTime('2025-07-10'));
        $event3->setPurchaseEndDate(new \DateTime('2025-09-01'));
        $event3->setOrganiser('Live Nation');
        $event3->setDescription
        ("Coldplay brings their Music of the Spheres tour to Singapore.");
        $event3->setImage('images/events/coldplay.jpg');
        $manager->persist($event3);

        $event4 = new Event();
        $event4->setVenue($venue1);
        $event4->setCategory($category2);
        $event4->setName('F1 Singapore Grand Prix 2025');
        $event4->setCapacity(70000);
        $event4->setPurchaseStartDate(new \DateTime('2025-06-01'));
        $event4->setPurchaseEndDate(new \DateTime('2025-09-15'));
        $event4->setOrganiser('Singapore GP Pte Ltd');
        $event4->setDescription
        ("The electrifying night race returns to the heart of the city. Feel the adrenaline at the iconic 
        night race through Marina Bay’s cityscape.");
        $event4->setImage('images/events/f1singapore.jpg');
        $manager->persist($event4);

        $event5 = new Event();
        $event5->setVenue($venue2);
        $event5->setCategory($category1);
        $event5->setName('JJ Lin: Sanctuary World Tour');
        $event5->setCapacity(45000);
        $event5->setPurchaseStartDate(new \DateTime('2025-06-20'));
        $event5->setPurchaseEndDate(new \DateTime('2025-08-10'));
        $event5->setOrganiser('Warner Music');
        $event5->setDescription
        ("Singapore’s Mandopop king returns home with his signature sound and heartfelt ballads to a stunning stage production.");
        $event5->setImage('images/events/jjlin_worldtour.jpg');
        $manager->persist($event5);

        $event6 = new Event();
        $event6->setVenue($venue3);
        $event6->setCategory($category3);
        $event6->setName('The Lion King Musical');
        $event6->setCapacity(3000);
        $event6->setPurchaseStartDate(new \DateTime('2025-06-15'));
        $event6->setPurchaseEndDate(new \DateTime('2025-08-20'));
        $event6->setOrganiser('Base Entertainment');
        $event6->setDescription
        ("Disney’s iconic Broadway show returns to MBS Theatre");
        $event6->setImage('images/events/lionking_musical.jpg');
        $manager->persist($event6);

        $event7 = new Event();
        $event7->setVenue($venue2);
        $event7->setCategory($category2);
        $event7->setName('ONE Championship: Singapore Fight Night');
        $event7->setCapacity(12000);
        $event7->setPurchaseStartDate(new \DateTime('2025-03-15'));
        $event7->setPurchaseEndDate(new \DateTime('2025-06-14'));
        $event7->setOrganiser('ONE Championship');
        $event7->setDescription
        ("Asia’s biggest MMA fighters clash in a thrilling night of knockouts, submissions, and drama.");
        $event7->setImage('images/events/one_championship.jpg');
        $manager->persist($event7);

        $event8 = new Event();
        $event8->setVenue($venue7);
        $event8->setCategory($category1);
        $event8->setName('Singapore Jazz Festival');
        $event8->setCapacity(5000);
        $event8->setPurchaseStartDate(new \DateTime('2025-07-15'));
        $event8->setPurchaseEndDate(new \DateTime('2025-08-15'));
        $event8->setOrganiser('Sing Jazz Pte Ltd');
        $event8->setDescription
        ("A weekend of smooth and soulful jazz by international and local artists at a scenic waterfront venue.");
        $event8->setImage('images/events/jazz_singapore.jpg');
        $manager->persist($event8);

        $event9 = new Event();
        $event9->setVenue($venue2);
        $event9->setCategory($category2);
        $event9->setName('Singapore vs Thailand Football Friendly');
        $event9->setCapacity(6000);
        $event9->setPurchaseStartDate(new \DateTime('2025-08-01'));
        $event9->setPurchaseEndDate(new \DateTime('2025-10-10'));
        $event9->setOrganiser('Football Association of Singapore');
        $event9->setDescription
        ("A competitive face-off between regional football giants in an electrifying match.");
        $event9->setImage('images/events/football.jpg');
        $manager->persist($event9);

        $event10 = new Event();
        $event10->setVenue($venue6);
        $event10->setCategory($category3);
        $event10->setName('Crazy Rich Asians: The Musical');
        $event10->setCapacity(1600);
        $event10->setPurchaseStartDate(new \DateTime('2025-05-15'));
        $event10->setPurchaseEndDate(new \DateTime('2025-08-13'));
        $event10->setOrganiser('Marina Bay Sands Theatre Co.');
        $event10->setDescription
        ("A glitzy stage adaptation of the beloved novel with laughs, drama and glamour set in Singapore.");
        $event10->setImage('images/events/crazy_rich_asians.jpg');
        $manager->persist($event10);

        $event11 = new Event();
        $event11->setVenue($venue1);
        $event11->setCategory($category2);
        $event11->setName('Standard Chartered Singapore Marathon');
        $event11->setCapacity(25000);
        $event11->setPurchaseStartDate(new \DateTime('2025-07-01'));
        $event11->setPurchaseEndDate(new \DateTime('2025-09-05'));
        $event11->setOrganiser('Ironman Asia');
        $event11->setDescription
        ("Join thousands of runners in Singapore’s premier marathon event. Categories include full, half, and 10km");
        $event11->setImage('images/events/stand_chart_run.jpg');
        $manager->persist($event11);

        $event12 = new Event();
        $event12->setVenue($venue9);
        $event12->setCategory($category1);
        $event12->setName('Taylor Swift The Eras Tour');
        $event12->setCapacity(50000);
        $event12->setPurchaseStartDate(new \DateTime('2025-07-05'));
        $event12->setPurchaseEndDate(new \DateTime('2025-08-05'));
        $event12->setOrganiser('AEG Presents');
        $event12->setDescription
        ("Global pop icon Taylor Swift takes you on a journey through her musical eras in this dazzling multi-night show.");
        $event12->setImage('images/events/taylor_swift.jpg');
        $manager->persist($event12);

        $event13 = new Event();
        $event13->setVenue($venue8);
        $event13->setCategory($category3);
        $event13->setName('The Phantom of the Opera');
        $event13->setCapacity(3000);
        $event13->setPurchaseStartDate(new \DateTime('2025-01-01'));
        $event13->setPurchaseEndDate(new \DateTime('2025-03-10'));
        $event13->setOrganiser('Base Entertainment Asia');
        $event13->setDescription
        ("The haunting love story unfolds with breathtaking music and iconic stage design. A global Broadway favourite.");
        $event13->setImage('images/events/phantom_of_the_opera.jpg');
        $manager->persist($event13);

        // // Create Event
        // $event = new Event();
        // $event->setName('Jazz Night');
        // $event->setDescription('A night of jazz music');
        // $event->setCapacity(500);
        // $event->setVenue($venue);
        // $event->setOrganiser('Jazz Org');
        // $event->setCategory($category);
        // $event->setPurchaseStartDate(new \DateTime('-7 days'));
        // $event->setPurchaseEndDate(new \DateTime('+7 days'));
        // $event->setImage('jazz.jpg');
        // $manager->persist($event);

        // // Create Ticket Type
        // $ticketType = new TicketType();
        // $ticketType->setName('General Admission');
        // $ticketType->setDescription('Standing area');
        // $ticketType->setPrice(50.00);
        // $manager->persist($ticketType);

        // // Create Payment
        // $payment = new Payment();
        // $payment->setUser($user);
        // $payment->setPaymentMethod('credit_card');
        // $payment->setPaymentDateTime(new \DateTime());
        // $payment->setTotalPrice(50.00);
        // $manager->persist($payment);

        // // Create History (placeholder)
        // $history = new History();
        // $history->setUser($user);
        // $history->setPayment($payment);
        // $history->setAction('Purchase');
        // $history->setTimestamp(new \DateTime());
        // $manager->persist($history);

        // // Create Ticket
        // $ticket = new Ticket();
        // $ticket->setEvent($event);
        // $ticket->setPayment($payment);
        // $ticket->setTicketType($ticketType);
        // $ticket->setSeatNumber('A1');
        // $ticket->setHistory($history);
        // $manager->persist($ticket);

        // // Create JWT Blacklist
        // $jwt = new JwtBlacklist();
        // $jwt->setUser($user);
        // $jwt->setExpiresAt((new \DateTime())->modify('+1 hour'));
        // $jwt->setRevokedAt(null); // not revoked
        // $manager->persist($jwt);

        $manager->flush();
    }
}
