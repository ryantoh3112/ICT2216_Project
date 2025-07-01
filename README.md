# GoTix #
GoTix is a web application designed as a platform for the public to purchase tickets for events in Singapore. The platform aims to bring exciting events to users, allowing them to conveniently buy tickets for a variety of occasions.

## GoTix Architecture
GoTix is a project we built for our module **ICT2216 Secure Software Development**. It utilizes the LEMP architecture, together with the Symfony framework and Docker for containerization.

## Group 25 Members
| Name            | Student ID | Major |
| :---------------- | :------: | :----: |
| Soo Jia En Charis       |   2302086   | Software Engineering |
| Shaw Aradhana           |   2302229   | Software Engineering |
| Toh Shun Cheng Ryan |  2302024   | Software Engineering |
| Leong Jia Hao |  2301809   | Information Security |
| Tan You An Michael |  2302145   | Information Security |
| Lim Guo Dong    |  2301766   | Information Security |
| Wu Yen Hao |  2301919   | Information Security |

## Set up Guide
### Prerequisites
* After cloning from the branch, ensure you have PHP (8.2 and above) and Composer (Different from Docker Compose) installed
* Install Symfony CLI using Scoop by following this: https://symfony.com/download
* Use an IDE (eg. Microsoft VS Code) to open the file and make use of the inbuilt terminal to run symfony commands (navigate to the project directory)

* Run ```symfony check:requirements``` to check if there are any missing packages, install them accordingly using ```composer install```.
![image2](https://github.com/user-attachments/assets/f693d5e5-1f8f-4ee0-ba94-79932281a6ab)
<br/>

* If there are missing packages, you should see this before using ```composer install``` to install the missing packages:
![image](https://github.com/user-attachments/assets/d5bfff61-8863-414f-8f97-fcd633e68ce3)
<br/>

* Run ```symfony check:requirements``` again to check if there are any more missing packages. You should see this now after successfully installing all required packages:
![image3](https://github.com/user-attachments/assets/f52c5094-2a12-4e4f-98ba-d8994ba1c0f4)
<br/>

* Once all packages installed, run ```symfony server:start``` to start the application in your own local host machine/device.
* If you want to start the application using Docker instead, run ```docker compose up --build -d```
![image4](https://github.com/user-attachments/assets/b59c00c1-05f7-48c3-be0c-dd6537a75387)
<br/>

> [!NOTE]  
> **_Make sure to not run ```symfony server:start``` if the docker containers are up and running_**

### Database setup
#### Local (```symfony server:start```)
1. Ensure you have MySQL (MySQL Server & MySQL Workbench etc.) installed in your local device.
2. Create a local ```.env.local``` file similar to the ```.env``` file, however change/edit the ```.env.local``` file as shown in step 3 to set your MySQL credentials to connect to your own local MySQL server.
3. In your ```.env.local``` file, edit this line:
   ```sh
   DATABASE_URL="mysql://db_user:db_pass@127.0.0.1:3306/db_name?serverVersion=8.0.32&charset=utf8mb4"
   ```
   * Replace ```db_user``` and ```db_pass``` based on your own MySQL database setup.
   * For ```db_name```, you can set to any name you want, this will be the name of the database in your own local setup.
4. Run the command below to create the database based on the DATABASE_URL you have setup in the ```.env.local``` file.
   ```sh
   php bin/console doctrine:database:create
   ```
   If the database already exists, you will see a message like this:<br/>
   ```Database "my_project_db" for connection named default already exists. Skipped.```
5. Run the command below to create the tables/schema in the database created from the previous step.
   ```sh
   php bin/console doctrine:migrations:migrate
   ```
6. To insert/load test data into the database, run:
   ```sh
   php bin/console doctrine:fixtures:load
   ```
   * You will see a message like this:<br/>
   ```Careful, database "_______" will be purged. Do you want to continue? (yes/no):```<br/>
   Just input 'yes', it will delete any existing data in the database and insert test data based on the fixture files.
   
