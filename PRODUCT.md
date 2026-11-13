# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

delegated: static HTML, CSS, and JavaScript for the frontend prototype because the repository is empty and the assigned task is a mock-data queue board. The target product stack remains Laravel with Blade and Livewire from the NgodingPakeAI PRD.

## Users

Primary users are Mobile Legends streamers running a live VIP mabar session. They need to see the queue at a glance and quickly add players, mark readiness, move a player, skip a turn, or remove an entry while streaming. Viewers are a secondary audience for later public queue and OBS surfaces.

## Product Purpose

MabarQueue helps streamers manage a VIP mabar queue fed by donations and manual entries. Success means a streamer can keep the live queue orderly, know who is playing now, and make the next queue action in a few clicks without maintaining a separate note.

## Positioning

The product combines VIP donation context, queue order, readiness, and match entitlement in one streamer-focused operating surface. Its distinct mechanism is an actionable queue board that keeps live decisions visible while the session is in progress.

## Operating Context

The dashboard is used during a live stream on a desktop or mobile browser. The streamer may also use an OBS browser source and later connect Saweria, SociaBuzz, or Trakteer webhooks. The first surface is a dashboard prototype using clearly synthetic queue data.

## Capabilities and Constraints

- The first frontend task covers a mock-data queue board for a live session.
- The queue must show order, current turn, ready/offline state, and fast management actions.
- Manual player entry is required in the queue workflow.
- The visual direction follows the PRD's Neo-Brutalism Gaming SaaS constraints with thick borders, hard shadows, high contrast, and minimal radius.
- MVP constraints include Laravel with Blade and Livewire, polling instead of WebSockets, no React, Next.js, Inertia, or separate SPA.
- Backend data and authentication are not available in this empty repository yet.

## Brand Commitments

The working product name is MabarQueue. The PRD's value proposition is "Donasi masuk, antrean VIP otomatis. Fokus main, bukan nyatet pemain." The product language is Indonesian.

## Evidence on Hand

The NgodingPakeAI PRD is the source of product requirements. No production logo, customer proof, or real queue data was supplied. Prototype entries must remain illustrative.

## Product Principles

- Keep the current turn obvious.
- Make live queue actions fast and reversible where possible.
- Preserve context about readiness, provider, and match entitlement.
- Prefer a readable operating view over hidden navigation during a live session.
- Label synthetic data honestly until backend integration exists.

## Accessibility & Inclusion

Keyboard focus must remain visible, status cannot rely on color alone, controls need clear action labels, and the layout must remain usable on mobile widths.
