# Word Puzzle Game 


# 🧩 Word Puzzle Game Backend (Symfony + MySQL)

This project is a RESTful backend system for a word puzzle game. Players are given a 14-letter random puzzle and can submit valid English words to score points. A leaderboard highlights top-performing submissions. Built with Symfony and powered by MySQL.

---

## ✨ What This Project Offers

### 🎮 Game Logic
- ✅ **Puzzle Generator**: Random 14-letter strings with guaranteed dictionary-valid words.
- ✅ **Word Validator**: Accepts only dictionary-valid, unused words from the puzzle letters.
- ✅ **Scoring System**: Awards 1 point per valid letter used in the word.
- ✅ **Letter Tracker**: Dynamically updates and prevents reuse of letters.
- ✅ **Leaderboard**: Top 10 unique submissions by score.
- ✅ **Session Handling**: Tracks ongoing puzzles and submissions per student.

### 🛠 Backend Engineering
- 🔌 **REST API**: Clean, stateless JSON-based endpoints.
- 💡 **Service Architecture**: Uses dependency injection and separation of concerns.
- ⚠️ **Error Handling**: Graceful exception management with meaningful responses.
- 📄 **Word Caching**: Caches word list from file for optimized performance.
- 🧪 **Unit Tests**: PHPUnit tests for core game services.
- 🗄️ **Database**: MySQL used with Doctrine ORM and migrations.

---

## 🧪 Tech Stack

| Layer         | Technology                     |
|---------------|--------------------------------|
| Framework     | Symfony 6                      |
| Language      | PHP 8.1+                        |
| ORM           | Doctrine                       |
| Database      | MySQL                          |
| Testing       | PHPUnit                        |
| Frontend      | HTML, CSS, JavaScript (jQuery) |
| Templating    | Twig (for web views)           |
| Tools         | Composer, Symfony CLI          |

## 📁 Project Structure

```
word-puzzle-symfony/
├── src/
│   ├── Controller/Api/GameController.php
│   ├── Controller/GameController.php
│   ├── Entity/
│   │   ├── Puzzle.php          # Puzzle entity
│   │   ├── Student.php         # Student/session entity
│   │   ├── Submission.php      # Word submission entity
│   │          
│   ├── Repository/
│   │   ├── PuzzleRepository.php
│   │   ├── StudentRepository.php
│   │   ├── SubmissionRepository.php
│   │   
│   ├── Service/
│   │   ├── PuzzleService.php    # Core game & logic
│   │   └── WordListService.php  # Dictionary integration
├── tests/
│   └── Service/PuzzleServiceTest.php
├── templates/game/index.html.twig
├── public/index.php
└── data/words.txt               # Dictionary file
```

## 🚀 Installation & Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- PostgreSQL (via Docker or local installation)

### Step 1: Clone and Install Dependencies
```bash
# Clone the repository
git clone <repository-url>
cd word_puzzle_symfony

# Install dependencies
composer install
```

### Step 2: Environment Configuration
```bash
# Copy environment file for development
cp .env .env.dev

# Edit .env.dev and configure:
# - Database URL (PostgreSQL for development)
# - App secret
# - Other environment variables
```

### Step 3: Database Setup
```bash
# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

### Step 4: Start Development Server
```bash
# Start Symfony development server
php -S localhost:8000 -t public/

# Or use Symfony CLI
symfony server:start
```

### Step 5: Access the Application
- **Web Interface**: http://localhost:8000

## API Endpoints

### 1. Create Puzzle
**POST** `/api/game/puzzle`

Creates a new puzzle for a student session.




### 2. Submit Word
**POST** `/api/game/submit`

Submit a word attempt for the current puzzle.


### 3. Get Puzzle State
**GET** `/api/game/state/{studentName}`



### 4. Get Leaderboard
**GET** `/api/game/leaderboard`



### Base URL
```
http://localhost:8000/api/game
```

#### 1.  Create Puzzle
```http
POST /api/game/puzzle
Content-Type: application/json
```

#### 2.  Submit Word
```http
POST /api/game/submit
Content-Type: application/json
```

#### 3.  Get Game State
```http
GET /api/game/state/{studentName}
```

#### 4.  Get Leaderboard
```http
GET /api/game/leaderboard
```

#### 5.  End Game
```http
POST /api/game/end
Content-Type: application/json
```

### Interactive Documentation
- **Swagger UI**: `http://localhost:8000/api/doc`
- **JSON Schema**: `http://localhost:8000/api/doc.json`

## 🎮 Game Workflow

### 1. **Game Initialization**
- Student enters session ID
- System creates new puzzle with 14 random letters
- Puzzle guaranteed to have at least one valid English word

### 2. **Word Submission Process**
- Student submits a word
- System validates:
  - Word is not empty
  - Word contains only letters
  - Word is not too long (max 14 characters)
  - Word is a valid English word
  - Word can be formed from remaining letters
  - Word hasn't been submitted before

### 3. **Scoring & Letter Management**
- Score = 1 point per letter used
- Used letters are removed from remaining pool
- Total score accumulates across submissions

### 4. **Game Completion**
- Game ends when:
  - No more valid words can be formed
  - Student manually ends the game
- System shows final score and remaining valid words

### 5. **Leaderboard Management**
- Top 10 highest-scoring unique words
- No duplicate words allowed
- Automatic cleanup of lower-scoring entries

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php bin/phpunit

# Run specific test file
php bin/phpunit tests/Service/GameServiceTest.php

# Run specific test method
php bin/phpunit --filter testSubmitWordSuccess

# Run with coverage
php bin/phpunit --coverage-html coverage/
```

### Test Coverage
The project includes comprehensive unit tests covering:

- **Game Creation**: New student, existing student scenarios
- **Word Submission**: Success cases, validation errors
- **Game State**: State retrieval, error handling
- **Leaderboard**: Score management, cleanup
- **Puzzle Logic**: Letter usage, scoring calculation
- **Game End**: Completion logic, remaining words

### Test Structure
```php
class GameServiceTest extends TestCase
{
    // Setup with mocked dependencies
    protected function setUp(): void
    {
        // Create mocks for all dependencies
        // Inject mocks into service
    }

    // Test methods follow AAA pattern:
    // Arrange - Set up test data and mocks
    // Act - Execute the method being tested
    // Assert - Verify expected outcomes
}
```

## 🔧 Development

### Key Services

#### PuzzleService
- **Purpose**: Core game logic and business rules
- **Responsibilities**:
  - Puzzle creation and management
  - Word submission validation
  - Score calculation
  - Game state management
  - Leaderboard updates

#### WordListService
- **Purpose**: Word validation and dictionary operations
- **Responsibilities**:
  - English word validation
  - Remaining word calculation
  - Dictionary caching
  - Word frequency analysis

### Database Schema

#### Puzzle Entity
- `id`: Primary key
- `puzzleString`: 14-letter puzzle string
- `remainingLetters`: Available letters for word formation
- `isActive`: Game status
- `createdAt`: Creation timestamp

#### Student Entity
- `id`: Primary key
- `name`: Unique student identifier
- `puzzle`: Associated puzzle
- `lastActivity`: Last activity timestamp

#### Submission Entity
- `id`: Primary key
- `word`: Submitted word
- `score`: Word score
- `puzzle`: Associated puzzle
- `submittedAt`: Submission timestamp


##  Deployment

### Production Setup

#### 1. Environment Configuration
```bash
# Set production environment
APP_ENV=prod
APP_DEBUG=0

# Configure database
DATABASE_URL="mysql://user:pass@host:port/database"

# Set secret
APP_SECRET=your-secret-key
```

#### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Database Setup
```bash
php bin/console doctrine:migrations:migrate --env=prod
```

#### 4. Clear Cache
```bash
php bin/console cache:clear --env=prod
```

#### 5. Web Server Configuration
Configure your web server (Apache/Nginx) to point to the `public/` directory.

### Docker Deployment
```dockerfile
FROM php:8.1-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-install zip pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data var/

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "public/"]
```

## 📝 Contributing

### Development Workflow
1. **Fork** the repository
2. **Create** a feature branch
3. **Write** tests for new functionality
4. **Implement** the feature
5. **Run** tests to ensure everything works
6. **Submit** a pull request

### Code Standards
- Follow PSR-12 coding standards
- Write comprehensive unit tests
- Document all public methods
- Use dependency injection
- Handle exceptions properly

