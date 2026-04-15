# Profile Intelligence API

This is a backend service built with PHP and Slim Framework. It accepts a name and enriches it with demographic data by connecting to three external APIs: Genderize, Agify, and Nationalize. The application processes this data, saves it to a SQLite database, and exposes a clean RESTful API to manage the stored profiles.

Repository: https://github.com/Yiranubari/gender-classify-api

## Features

- Integrates with multiple third party APIs.
- Classifies users into age groups (child, teenager, adult, senior).
- Generates UUID v7 identifiers for all records.
- Stores creation timestamps in UTC format.
- Prevents duplicate entries (idempotent profile creation).
- Supports filtering profiles by gender, country, and age group.
- Handles external API errors securely.

## Tech Stack

- PHP 8.2
- Slim Framework 4
- SQLite (PDO)
- PHP-DI (Dependency Injection)
- PHPUnit (Testing)

## API Endpoints

### 1. Create a Profile
Creates a new profile or returns the existing one if the name is already in the database.

**Request:**
`POST /api/profiles`
```json
{
  "name": "ella"
}
```

**Success Response (201 Created):**
```json
{
  "status": "success",
  "data": {
    "id": "019d9134-7e8a-739f-bdd7-eab39bb9ce0a",
    "name": "ella",
    "gender": "female",
    "gender_probability": 0.99,
    "sample_size": 97517,
    "age": 46,
    "age_group": "adult",
    "country_id": "US",
    "country_probability": 0.85,
    "created_at": "2026-04-15T12:00:00Z"
  }
}
```

### 2. Get a Specific Profile
Retrieves a single profile by its UUID.

**Request:**
`GET /api/profiles/{id}`

**Success Response (200 OK):**
```json
{
  "status": "success",
  "data": {
    "id": "019d9134-7e8a-739f-bdd7-eab39bb9ce0a",
    "name": "ella",
    "gender": "female",
    "gender_probability": 0.99,
    "sample_size": 97517,
    "age": 46,
    "age_group": "adult",
    "country_id": "US",
    "country_probability": 0.85,
    "created_at": "2026-04-15T12:00:00Z"
  }
}
```

### 3. List All Profiles
Retrieves a list of profiles. You can filter the results using query parameters.

**Request:**
`GET /api/profiles?gender=female&age_group=adult`

**Success Response (200 OK):**
```json
{
  "status": "success",
  "count": 1,
  "data": [
    {
      "id": "019d9134-7e8a-739f-bdd7-eab39bb9ce0a",
      "name": "ella",
      "gender": "female",
      "age": 46,
      "age_group": "adult",
      "country_id": "US"
    }
  ]
}
```

### 4. Delete a Profile
Deletes a profile by its UUID.

**Request:**
`DELETE /api/profiles/{id}`

**Success Response (204 No Content)**
Returns no body content.

## Local Setup

1. Clone the repository:
```bash
git clone https://github.com/Yiranubari/gender-classify-api.git
cd gender-classify-api
```

2. Install dependencies:
```bash
composer install
```

3. Start the development server:
```bash
php -S localhost:8080 -t public
```

Alternatively, if you are using Docker, you can build and run the provided Dockerfile.

## Testing

To run the automated test suite, use PHPUnit:
```bash
./vendor/bin/phpunit
```
