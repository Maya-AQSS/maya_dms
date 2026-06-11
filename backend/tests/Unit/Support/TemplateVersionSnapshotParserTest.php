<?php

declare(strict_types=1);

use App\Support\TemplateVersionSnapshotParser;

describe('TemplateVersionSnapshotParser::authorId()', function () {
    it('extracts author id from array snapshot', function () {
        $snapshot = ['template' => ['created_by' => 'uuid-author-1']];

        expect(TemplateVersionSnapshotParser::authorId($snapshot))->toBe('uuid-author-1');
    });

    it('extracts author id from JSON string snapshot', function () {
        $snapshot = json_encode(['template' => ['created_by' => 'uuid-author-2']], JSON_THROW_ON_ERROR);

        expect(TemplateVersionSnapshotParser::authorId($snapshot))->toBe('uuid-author-2');
    });

    it('returns null when created_by key is absent', function () {
        $snapshot = ['template' => ['name' => 'foo']];

        expect(TemplateVersionSnapshotParser::authorId($snapshot))->toBeNull();
    });

    it('returns null when template key is absent', function () {
        $snapshot = ['reviewers' => []];

        expect(TemplateVersionSnapshotParser::authorId($snapshot))->toBeNull();
    });

    it('returns null when created_by is empty string', function () {
        $snapshot = ['template' => ['created_by' => '']];

        expect(TemplateVersionSnapshotParser::authorId($snapshot))->toBeNull();
    });

    it('returns null for null input', function () {
        expect(TemplateVersionSnapshotParser::authorId(null))->toBeNull();
    });

    it('returns null for empty string input', function () {
        expect(TemplateVersionSnapshotParser::authorId(''))->toBeNull();
    });

    it('returns null for invalid JSON string', function () {
        expect(TemplateVersionSnapshotParser::authorId('not-json'))->toBeNull();
    });
});

describe('TemplateVersionSnapshotParser::reviewerIds()', function () {
    it('extracts reviewer ids from array snapshot', function () {
        $snapshot = [
            'reviewers' => [
                'template_reviewers' => [
                    ['user_id' => 'uuid-r1', 'stage' => 1],
                    ['user_id' => 'uuid-r2', 'stage' => 2],
                ],
            ],
        ];

        expect(TemplateVersionSnapshotParser::reviewerIds($snapshot))->toBe(['uuid-r1', 'uuid-r2']);
    });

    it('extracts reviewer ids from JSON string snapshot', function () {
        $snapshot = json_encode([
            'reviewers' => [
                'template_reviewers' => [
                    ['user_id' => 'uuid-r3'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        expect(TemplateVersionSnapshotParser::reviewerIds($snapshot))->toBe(['uuid-r3']);
    });

    it('returns empty array when reviewers key is absent', function () {
        $snapshot = ['template' => ['created_by' => 'uuid-author']];

        expect(TemplateVersionSnapshotParser::reviewerIds($snapshot))->toBe([]);
    });

    it('returns empty array when template_reviewers is empty', function () {
        $snapshot = ['reviewers' => ['template_reviewers' => []]];

        expect(TemplateVersionSnapshotParser::reviewerIds($snapshot))->toBe([]);
    });

    it('returns empty array when template_reviewers is not array', function () {
        $snapshot = ['reviewers' => ['template_reviewers' => null]];

        expect(TemplateVersionSnapshotParser::reviewerIds($snapshot))->toBe([]);
    });

    it('skips reviewer entries with empty user_id', function () {
        $snapshot = [
            'reviewers' => [
                'template_reviewers' => [
                    ['user_id' => ''],
                    ['user_id' => 'uuid-valid'],
                    ['stage' => 1],
                ],
            ],
        ];

        expect(TemplateVersionSnapshotParser::reviewerIds($snapshot))->toBe(['uuid-valid']);
    });

    it('returns empty array for null input', function () {
        expect(TemplateVersionSnapshotParser::reviewerIds(null))->toBe([]);
    });

    it('returns empty array for empty string input', function () {
        expect(TemplateVersionSnapshotParser::reviewerIds(''))->toBe([]);
    });

    it('returns empty array for invalid JSON string', function () {
        expect(TemplateVersionSnapshotParser::reviewerIds('not-json'))->toBe([]);
    });
});
