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
        // // Create User
        // $user = new User();
        // $user->setName('John Doe');
        // $user->setRole('ROLE_USER');
        // $user->setCreatedAt(new \DateTime());
        // $user->setUpdatedAt(new \DateTime());
        // $user->setLastLoginAt(new \DateTime());
        // $user->setFailedLoginCount(0);
        // $user->setAccountStatus('active');
        // $manager->persist($user);

        // // Create Auth
        // $auth = new Auth();
        // $auth->setUser($user);
        // $auth->setEmail('john@example.com');
        // $auth->setPassword(password_hash('password', PASSWORD_ARGON2I));
        // $manager->persist($auth);

        // // Create Category
        // $category = new EventCategory();
        // $category->setName('Music');
        // $category->setDescription('Live music events');
        // $category->setImage('music.jpg');
        // $manager->persist($category);

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
