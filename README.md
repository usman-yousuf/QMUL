## Project Name
Queen Mary Alumni

<strong>Prerequisites</strong><br>
    PHP 7.4<br>
    Composer<br>
    Laravel 8<br>
    Clone the Repository<br>
    First, clone the repository from the desired branch:

    git clone -b your-branch <repository-url>
    Click the Clone button and copy the url
                    OR
    Download the Repository
    If you prefer to download the project instead of cloning it, you can do so. Go to the main page of the repository, click the button near clone option, then click Download repository.


<strong>Setup Instructions</strong><br>
After you clone or download the project, navigate to the project directory:

    cd <project-directory>

If custom .env file is given add that into the root dir <br> 
                or<br>
If a .env.example file exists, copy it to a new file named .env:

    cp .env.example .env

Delete the vendor directory and the composer.lock file:

    rm -rf vendor/
    rm composer.lock

Install the Composer dependencies:

    composer install

Generate a new application key:

    php artisan key:generate

Running the project<br>
To run the project locally, use the command:

    php artisan serve
