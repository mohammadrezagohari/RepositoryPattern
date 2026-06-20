<?php

namespace Gohari\RepositoryPattern\Tests\Unit;

use Gohari\RepositoryPattern\BaseRepository;
use Gohari\RepositoryPattern\Tests\Fixtures\SoftDeleteUser;
use Gohari\RepositoryPattern\Tests\Fixtures\TestUser;
use Gohari\RepositoryPattern\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class BaseRepositoryTest extends TestCase
{
    private BaseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new BaseRepository(new TestUser);
    }

    public function test_it_returns_a_fresh_query_builder(): void
    {
        $this->assertInstanceOf(Builder::class, $this->repository->query());
    }

    public function test_it_gets_all_models(): void
    {
        TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);
        TestUser::query()->create(['name' => 'Sara', 'email' => 'sara@example.com']);

        $users = $this->repository->getAll();

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(2, $users);
    }

    public function test_it_paginates_models(): void
    {
        TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);
        TestUser::query()->create(['name' => 'Sara', 'email' => 'sara@example.com']);

        $users = $this->repository->paginate(1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $users);
        $this->assertSame(1, $users->perPage());
        $this->assertSame(2, $users->total());
    }

    public function test_it_finds_a_model_by_id(): void
    {
        $user = TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $found = $this->repository->findById($user->id);

        $this->assertTrue($user->is($found));
    }

    public function test_it_finds_first_model_by_column(): void
    {
        TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $found = $this->repository->firstWhere('email', 'ali@example.com');

        $this->assertSame('Ali', $found?->getAttribute('name'));
        $this->assertTrue($found->is($this->repository->findBy('email', 'ali@example.com')));
    }

    public function test_it_checks_existence_and_counts_models(): void
    {
        $user = TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $this->assertTrue($this->repository->exists($user->id));
        $this->assertFalse($this->repository->exists(999));
        $this->assertSame(1, $this->repository->count());
    }

    public function test_it_returns_null_when_model_is_not_found_by_id(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function test_it_finds_a_model_or_fails(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOrFail(999);
    }

    public function test_it_creates_a_model(): void
    {
        $user = $this->repository->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $this->assertTrue($user->exists);
        $this->assertDatabaseHas('repository_pattern_test_users', ['email' => 'ali@example.com']);
    }

    public function test_it_updates_or_creates_a_model(): void
    {
        $user = $this->repository->updateOrCreate(
            ['email' => 'ali@example.com'],
            ['name' => 'Ali']
        );

        $this->assertTrue($user->exists);
        $this->assertDatabaseHas('repository_pattern_test_users', ['email' => 'ali@example.com', 'name' => 'Ali']);

        $updated = $this->repository->updateOrCreate(
            ['email' => 'ali@example.com'],
            ['name' => 'Updated Ali']
        );

        $this->assertTrue($user->is($updated));
        $this->assertDatabaseHas('repository_pattern_test_users', ['email' => 'ali@example.com', 'name' => 'Updated Ali']);
    }

    public function test_it_updates_a_model(): void
    {
        $user = TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $updated = $this->repository->update($user->id, ['name' => 'Reza']);

        $this->assertTrue($updated);
        $this->assertDatabaseHas('repository_pattern_test_users', ['id' => $user->id, 'name' => 'Reza']);
    }

    public function test_it_returns_false_when_updating_missing_model(): void
    {
        $this->assertFalse($this->repository->update(999, ['name' => 'Nobody']));
    }

    public function test_it_deletes_a_model(): void
    {
        $user = TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $deleted = $this->repository->delete($user->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('repository_pattern_test_users', ['id' => $user->id]);
    }

    public function test_it_returns_false_when_deleting_missing_model(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }

    public function test_it_deletes_many_models(): void
    {
        $first = TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);
        $second = TestUser::query()->create(['name' => 'Sara', 'email' => 'sara@example.com']);

        $this->assertSame(2, $this->repository->deleteMany([$first->id, $second->id]));
        $this->assertSame(0, $this->repository->count());
    }

    public function test_it_searches_by_column(): void
    {
        TestUser::query()->create(['name' => 'Ali Gohari', 'email' => 'ali@example.com']);
        TestUser::query()->create(['name' => 'Sara Ahmadi', 'email' => 'sara@example.com']);

        $results = $this->repository->searchByColumn('name', 'Gohari')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Ali Gohari', $results->first()?->getAttribute('name'));
    }

    public function test_it_searches_with_configurable_operator(): void
    {
        TestUser::query()->create(['name' => 'Ali Gohari', 'email' => 'ali@example.com']);
        TestUser::query()->create(['name' => 'Sara Ahmadi', 'email' => 'sara@example.com']);

        $likeResults = $this->repository->search('name', 'Gohari')->get();
        $exactResults = $this->repository->search('email', 'sara@example.com', '=')->get();

        $this->assertCount(1, $likeResults);
        $this->assertSame('Ali Gohari', $likeResults->first()?->getAttribute('name'));
        $this->assertCount(1, $exactResults);
        $this->assertSame('Sara Ahmadi', $exactResults->first()?->getAttribute('name'));
    }

    public function test_it_sorts_models(): void
    {
        TestUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);
        TestUser::query()->create(['name' => 'Sara', 'email' => 'sara@example.com']);

        $results = $this->repository->sortBy('name', 'desc')->pluck('name')->all();

        $this->assertSame(['Sara', 'Ali'], $results);
    }

    public function test_it_returns_query_with_relationships(): void
    {
        $query = $this->repository->withRelations([]);

        $this->assertInstanceOf(Builder::class, $query);
    }

    public function test_it_returns_fluent_repository_with_relationships(): void
    {
        $repository = $this->repository->with([]);

        $this->assertInstanceOf(BaseRepository::class, $repository);
        $this->assertNotSame($this->repository, $repository);
    }

    public function test_it_restores_and_force_deletes_soft_deleted_models(): void
    {
        $repository = new BaseRepository(new SoftDeleteUser);
        $user = SoftDeleteUser::query()->create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $this->assertTrue($repository->delete($user->id));
        $this->assertSame(1, $repository->onlyTrashed()->count());

        $this->assertTrue($repository->restore($user->id));
        $this->assertSame(0, $repository->onlyTrashed()->count());

        $this->assertTrue($repository->forceDelete($user->id));
        $this->assertDatabaseMissing('repository_pattern_test_users', ['id' => $user->id]);
    }

    public function test_legacy_aliases_call_their_modern_methods(): void
    {
        $user = $this->repository->insertData(['name' => 'Ali', 'email' => 'ali@example.com']);

        $this->assertTrue($user->exists);
        $this->assertTrue($this->repository->updateItem($user->getKey(), ['name' => 'Updated Ali']));
        $this->assertDatabaseHas('repository_pattern_test_users', ['id' => $user->getKey(), 'name' => 'Updated Ali']);
        $this->assertTrue($this->repository->deleteData($user->getKey()));
        $this->assertDatabaseMissing('repository_pattern_test_users', ['id' => $user->getKey()]);
    }
}
