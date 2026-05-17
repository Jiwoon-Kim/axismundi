---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-worker-threads/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: worker-threads
parent_order: 3
sub_order: 9
page_order: 119
title: "@wordpress/worker-threads"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/worker-threads

Utilities for type-safe Web Worker communication with RPC (Remote Procedure Call).

This package provides a simple and efficient way to communicate between the main thread and Web Workers, automatically handling:

- Promise-based async method calls
- Automatic transferable detection (ArrayBuffer, etc.)
- Type-safe API with full TypeScript support
- Error propagation from worker to main thread

## Installation

Install the module:

```bash
npm install @wordpress/worker-threads
```

## Usage

### Worker Thread

Create a worker file that exposes methods to the main thread:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// worker.tsimport { expose } from '@wordpress/worker-threads/worker'; const api = { async processImage( buffer: ArrayBuffer, quality: number ): Promise<ArrayBuffer> { // ... image processing logic return resultBuffer; }, async calculateSum( a: number, b: number ): Promise<number> { return a + b; },}; expose( api ); // Export the type for use with wrap() on the main threadexport type WorkerAPI = typeof api;
```

### Main Thread

Wrap the worker to get a type-safe proxy:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// main.tsimport { wrap, terminate } from '@wordpress/worker-threads';import type { WorkerAPI } from './worker'; // Create the workerconst worker = new Worker( new URL( './worker.js', import.meta.url ), { type: 'module',} ); // Wrap it to get the RPC proxyconst api = wrap<WorkerAPI>( worker ); // Call methods as async functionsconst result = await api.processImage( imageBuffer, 0.82 );const sum = await api.calculateSum( 1, 2 ); // Clean up when doneterminate( api );
```

## API Reference

### Main Thread API

#### wrap&lt;T&gt;( worker: Worker ): Remote&lt;T&gt;

Wraps a Worker to provide a type-safe RPC interface. The returned proxy object allows calling methods on the worker as if they were local async functions.

#### terminate( remote: Remote&lt;unknown&gt; ): void

Terminates a wrapped worker and cleans up resources. Any pending calls will be rejected.

### Worker Thread API

#### expose&lt;T&gt;( target: T ): void

Exposes an object’s methods to be called from the main thread. Should be called once in the worker script.

### Types

#### Remote&lt;T&gt;

A type that converts all methods of `T` to their async versions. Each method returns `Promise<Awaited<ReturnType>>`.

## Features

### Automatic Transferable Detection

The package automatically detects and transfers `ArrayBuffer` and other transferable objects, providing zero-copy performance for large data:

```js
// ArrayBuffers are automatically transferred, not copiedconst result = await api.processImage( largeImageBuffer );
```

### Error Handling

Errors thrown in the worker are properly propagated to the main thread:

```js
try { await api.riskyOperation();} catch ( error ) { console.error( 'Worker error:', error.message );}
```

### TypeScript Support

Full type inference for method signatures:

```js
// TypeScript knows the return type and parameter typesconst result: ArrayBuffer = await api.processImage( buffer, 0.82 );
```

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
