# Test Database Setup Guide

This guide explains how to configure test databases for each supported stack in Sofia4Builders.

## Core Requirements (All Stacks)

| Requirement | Why |
|-------------|-----|
| **Use real database** | SQLite behaves differently than MySQL/PostgreSQL |
| **Use transactions** | Automatic rollback keeps tests isolated |
| **Separate connection** | Don't pollute development data |
| **Fast reset** | Tests should run quickly |

---

## Laravel / PHP

### 1. Create Test Database

```bash
# MySQL
mysql -u root -p -e "CREATE DATABASE myapp_testing;"

# PostgreSQL
createdb myapp_testing
```

### 2. Configure phpunit.xml

```xml
<phpunit>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_DATABASE" value="myapp_testing"/>
    </php>
</phpunit>
```

### 3. Configure TestCase.php

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;  // ← This wraps each test in a transaction
}
```

### 4. Alternative: config/database.php

```php
'testing' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'database' => env('DB_DATABASE_TESTING', 'myapp_testing'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    // ... other options
],
```

### 5. Run Migrations for Test DB

```bash
php artisan migrate --database=testing
```

### Common Mistakes to Avoid

- **DON'T** use `RefreshDatabase` unless you need to - it's slower
- **DON'T** use SQLite in-memory - behavior differs from MySQL/PostgreSQL
- **DON'T** forget to run migrations on test database

---

## Node.js / TypeScript (Prisma)

### 1. Create Test Database

```bash
# PostgreSQL
createdb myapp_test

# MySQL
mysql -u root -p -e "CREATE DATABASE myapp_test;"
```

### 2. Configure .env.test

```env
DATABASE_URL="postgresql://user:password@localhost:5432/myapp_test"
```

### 3. Configure jest.setup.ts

```typescript
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

beforeAll(async () => {
  // Run migrations
  await prisma.$executeRaw`SELECT 1`; // Verify connection
});

beforeEach(async () => {
  // Start transaction
  await prisma.$executeRaw`BEGIN`;
});

afterEach(async () => {
  // Rollback transaction
  await prisma.$executeRaw`ROLLBACK`;
});

afterAll(async () => {
  await prisma.$disconnect();
});

export { prisma };
```

### 4. Configure package.json

```json
{
  "scripts": {
    "test": "dotenv -e .env.test -- jest",
    "test:migrate": "dotenv -e .env.test -- prisma migrate deploy"
  }
}
```

### 5. Alternative: Isolated Transactions per Test

```typescript
// test-utils.ts
import { PrismaClient } from '@prisma/client';

export async function withTransaction<T>(
  fn: (prisma: PrismaClient) => Promise<T>
): Promise<T> {
  const prisma = new PrismaClient();

  try {
    await prisma.$executeRaw`BEGIN`;
    const result = await fn(prisma);
    await prisma.$executeRaw`ROLLBACK`;
    return result;
  } finally {
    await prisma.$disconnect();
  }
}

// Usage in tests
it('creates a user', async () => {
  await withTransaction(async (prisma) => {
    const user = await prisma.user.create({ data: { name: 'Test' } });
    expect(user.name).toBe('Test');
    // Automatically rolled back
  });
});
```

---

## Django / Python

### 1. Create Test Database

Django creates test databases automatically, but you need a real database server.

```bash
# PostgreSQL
createdb myapp_test

# Or let Django create it (needs CREATE DATABASE permission)
```

### 2. Configure settings/test.py

```python
from .base import *

DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.postgresql',
        'NAME': 'myapp_test',
        'USER': 'postgres',
        'PASSWORD': 'password',
        'HOST': 'localhost',
        'PORT': '5432',
        'TEST': {
            'NAME': 'myapp_test',  # Use same DB, don't create new
        },
    }
}

# Speed up password hashing in tests
PASSWORD_HASHERS = [
    'django.contrib.auth.hashers.MD5PasswordHasher',
]
```

### 3. Configure pytest (recommended)

```ini
# pytest.ini
[pytest]
DJANGO_SETTINGS_MODULE = myapp.settings.test
python_files = tests.py test_*.py
```

```python
# conftest.py
import pytest

@pytest.fixture(autouse=True)
def enable_db_access_for_all_tests(db):
    """Enable database access for all tests."""
    pass

@pytest.fixture
def transactional_db(db, transactional_db):
    """Use transactional database for this test."""
    return transactional_db
```

### 4. Using TransactionTestCase

```python
from django.test import TransactionTestCase

class MyTest(TransactionTestCase):
    def test_something(self):
        # This test runs in a transaction
        # Rolled back after test completes
        pass
```

### 5. Run Tests

```bash
# With pytest
pytest --reuse-db  # Reuse test DB between runs

# With Django
python manage.py test --keepdb  # Keep test DB
```

---

## FastAPI / Python (SQLAlchemy)

### 1. Create Test Database

```bash
createdb myapp_test
```

### 2. Configure conftest.py

```python
import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from app.database import Base, get_db
from app.main import app

TEST_DATABASE_URL = "postgresql://user:password@localhost/myapp_test"

engine = create_engine(TEST_DATABASE_URL)
TestingSessionLocal = sessionmaker(bind=engine)

@pytest.fixture(scope="session", autouse=True)
def setup_database():
    """Create tables once for all tests."""
    Base.metadata.create_all(bind=engine)
    yield
    Base.metadata.drop_all(bind=engine)

@pytest.fixture
def db_session():
    """Create a new database session with transaction rollback."""
    connection = engine.connect()
    transaction = connection.begin()
    session = TestingSessionLocal(bind=connection)

    yield session

    session.close()
    transaction.rollback()
    connection.close()

@pytest.fixture
def client(db_session):
    """Create test client with database session override."""
    def override_get_db():
        yield db_session

    app.dependency_overrides[get_db] = override_get_db

    from fastapi.testclient import TestClient
    with TestClient(app) as c:
        yield c

    app.dependency_overrides.clear()
```

### 3. Using Async (SQLAlchemy 2.0)

```python
import pytest
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession
from sqlalchemy.orm import sessionmaker

TEST_DATABASE_URL = "postgresql+asyncpg://user:password@localhost/myapp_test"

async_engine = create_async_engine(TEST_DATABASE_URL)
AsyncTestingSession = sessionmaker(async_engine, class_=AsyncSession)

@pytest.fixture
async def async_db_session():
    async with async_engine.connect() as connection:
        async with connection.begin() as transaction:
            async with AsyncTestingSession(bind=connection) as session:
                yield session
                await transaction.rollback()
```

---

## Go (with sqlx)

### 1. Create Test Database

```bash
createdb myapp_test
```

### 2. Configure test_helpers.go

```go
package testutil

import (
    "database/sql"
    "os"
    "testing"

    _ "github.com/lib/pq"
)

var testDB *sql.DB

func init() {
    dsn := os.Getenv("TEST_DATABASE_URL")
    if dsn == "" {
        dsn = "postgres://localhost/myapp_test?sslmode=disable"
    }

    var err error
    testDB, err = sql.Open("postgres", dsn)
    if err != nil {
        panic(err)
    }
}

// SetupTestDB returns a transaction that will be rolled back after test
func SetupTestDB(t *testing.T) *sql.Tx {
    tx, err := testDB.Begin()
    if err != nil {
        t.Fatalf("failed to begin transaction: %v", err)
    }

    t.Cleanup(func() {
        tx.Rollback()
    })

    return tx
}

// WithTestTx wraps a function in a transaction
func WithTestTx(t *testing.T, fn func(tx *sql.Tx)) {
    tx := SetupTestDB(t)
    fn(tx)
    // tx.Rollback() called automatically by Cleanup
}
```

### 3. Using in Tests

```go
package user_test

import (
    "testing"
    "myapp/testutil"
    "myapp/repository"
)

func TestCreateUser(t *testing.T) {
    testutil.WithTestTx(t, func(tx *sql.Tx) {
        repo := repository.NewUserRepository(tx)

        user, err := repo.Create("test@example.com")
        if err != nil {
            t.Fatalf("failed to create user: %v", err)
        }

        if user.Email != "test@example.com" {
            t.Errorf("expected email test@example.com, got %s", user.Email)
        }
        // Transaction rolled back automatically
    })
}
```

### 4. Using testcontainers (Alternative)

```go
package integration_test

import (
    "context"
    "testing"

    "github.com/testcontainers/testcontainers-go"
    "github.com/testcontainers/testcontainers-go/modules/postgres"
)

func TestWithPostgres(t *testing.T) {
    ctx := context.Background()

    pgContainer, err := postgres.RunContainer(ctx,
        testcontainers.WithImage("postgres:15"),
        postgres.WithDatabase("test"),
        postgres.WithUsername("test"),
        postgres.WithPassword("test"),
    )
    if err != nil {
        t.Fatal(err)
    }

    t.Cleanup(func() {
        pgContainer.Terminate(ctx)
    })

    connStr, _ := pgContainer.ConnectionString(ctx, "sslmode=disable")
    // Use connStr to connect to database
}
```

---

## Rust (with sqlx)

### 1. Create Test Database

```bash
createdb myapp_test
```

### 2. Using sqlx::test Macro (Recommended)

```rust
// Cargo.toml
[dev-dependencies]
sqlx = { version = "0.7", features = ["runtime-tokio", "postgres", "testing"] }

// tests/user_test.rs
use sqlx::PgPool;

#[sqlx::test]
async fn test_create_user(pool: PgPool) -> sqlx::Result<()> {
    // Each test runs in its own transaction
    // Automatically rolled back at the end

    sqlx::query("INSERT INTO users (email) VALUES ($1)")
        .bind("test@example.com")
        .execute(&pool)
        .await?;

    let count: (i64,) = sqlx::query_as("SELECT COUNT(*) FROM users")
        .fetch_one(&pool)
        .await?;

    assert_eq!(count.0, 1);

    Ok(())  // Transaction rolled back here
}
```

### 3. Configure .env.test

```env
DATABASE_URL=postgres://localhost/myapp_test
```

### 4. Using Diesel

```rust
use diesel::prelude::*;
use diesel::pg::PgConnection;

fn establish_test_connection() -> PgConnection {
    let database_url = std::env::var("TEST_DATABASE_URL")
        .unwrap_or_else(|_| "postgres://localhost/myapp_test".to_string());
    PgConnection::establish(&database_url)
        .expect("Error connecting to test database")
}

#[test]
fn test_create_user() {
    let conn = establish_test_connection();

    conn.test_transaction::<_, diesel::result::Error, _>(|conn| {
        // All operations here are rolled back
        diesel::insert_into(users::table)
            .values(NewUser { email: "test@example.com" })
            .execute(conn)?;

        let count = users::table.count().get_result::<i64>(conn)?;
        assert_eq!(count, 1);

        Ok(())
    });
}
```

### 5. Run Tests

```bash
# Set environment variable
export DATABASE_URL=postgres://localhost/myapp_test

# Run migrations
sqlx migrate run

# Run tests
cargo test
```

---

## Troubleshooting

### "Database does not exist"

```bash
# Create the test database manually
createdb myapp_test  # PostgreSQL
mysql -e "CREATE DATABASE myapp_test"  # MySQL
```

### "Permission denied"

```bash
# Grant permissions
psql -c "GRANT ALL ON DATABASE myapp_test TO myuser"
```

### "Tests are slow"

1. Use transactions (not table truncation)
2. Disable foreign key checks if possible
3. Use faster password hashers in tests
4. Run tests in parallel with isolated connections

### "Data persists between tests"

1. Verify you're using transactions with rollback
2. Check if any test commits explicitly
3. Ensure each test gets a fresh transaction

### "Different behavior than production"

1. **Don't use SQLite** - use same DB engine as production
2. Check for database-specific SQL syntax
3. Verify timezone settings match

---

## Quick Reference

| Stack | Transaction Method | Config File |
|-------|-------------------|-------------|
| Laravel | `DatabaseTransactions` trait | `phpunit.xml` |
| Node/Prisma | `BEGIN`/`ROLLBACK` raw SQL | `jest.setup.ts` |
| Django | `TransactionTestCase` | `pytest.ini` |
| FastAPI | `session.begin()` / `rollback()` | `conftest.py` |
| Go | `tx.Begin()` / `tx.Rollback()` | `test_helpers.go` |
| Rust | `#[sqlx::test]` or `test_transaction` | `.env.test` |
