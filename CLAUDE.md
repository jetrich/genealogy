# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 genealogy application built with the TallStack (Tailwind CSS, Alpine.js, Laravel, Livewire). It's a family tree management system with multi-tenancy support via Laravel Jetstream Teams, featuring GEDCOM import/export capabilities.

## Core Architecture

### Database Models
- **Person**: Main entity with genealogical data, supporting media attachments and activity logging
- **Couple**: Relationship model connecting two people with marriage/partnership data
- **Team**: Multi-tenancy via Laravel Jetstream for family tree separation
- **User**: Authentication with team-based permissions (Administrator, Manager, Editor, Member)

### Key Relationships
- Person model uses Recursive CTEs for ancestors/descendants queries to avoid N+1 problems
- Couples link two Person models with relationship metadata
- Teams provide data isolation between different family trees
- Activity logging tracks all CRUD operations on persons, couples, and teams

### Frontend Architecture
- **Livewire Components**: Located in `app/Livewire/` for interactive family tree management
- **Blade Views**: In `resources/views/` with layouts, components, and page templates
- **TallStackUI**: Provides UI components with Tabler Icons
- **Multi-language**: Support for 11 languages with translations in `lang/` directories

## Development Commands

### Installation & Setup
```bash
composer install
php artisan key:generate
php artisan storage:link
php artisan migrate:fresh --seed
npm install && npm run build
```

### Development
```bash
# Start development server
php artisan serve

# Start frontend development with hot reloading
npm run dev

# Build for production
npm run build
```

### Testing
```bash
# Run all tests with Pest
php artisan test
# or
./vendor/bin/pest

# Check translation integrity
php artisan translations:check --excludedDirectories=vendor
```

### Code Quality
```bash
# Run Laravel Pint for code formatting
./vendor/bin/pint

# Generate IDE helper files
php artisan ide-helper:generate
```

### Database
```bash
# Fresh migration with demo data
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=DemoSeeder
```

## Key Features

### GEDCOM Support
- Import/Export functionality in `app/Php/Gedcom/`
- Uses custom PhpGedcom library in `PhpGedcom/` and `Gedcom/` directories
- Handles genealogical data exchange with other family tree software

### Media Management
- Photo and document uploads using Spatie MediaLibrary
- Watermarking support for images
- File type icons and carousel navigation

### Activity Logging
- Comprehensive audit trail for all person, couple, and team changes
- Viewable in People logbook and Team logbook interfaces
- Powered by Spatie ActivityLog package

### Backup System
- Built-in backup manager using Spatie Laravel Backup
- Automated scheduling support with email notifications
- Manual backup initiation through UI

### Developer Tools
- Built-in log viewer (Opcodesio Log Viewer)
- User management and statistics
- Debug logging for queries, requests, and performance monitoring

## Testing Configuration

- Uses Pest PHP testing framework
- Separate MySQL testing database configured in `phpunit.xml`
- Feature and Unit test structure in `tests/` directory
- Database factories for Person, Couple, Team, and User models

## File Organization

### Application Structure
- `app/Models/`: Eloquent models with relationships and scopes
- `app/Livewire/`: Interactive components for family tree management
- `app/Http/Controllers/`: Traditional controllers for pages and API endpoints
- `app/Rules/`: Custom validation rules for genealogical data
- `app/Policies/`: Authorization policies for teams and users

### Frontend Assets
- `resources/views/`: Blade templates organized by feature
- `resources/css/app.css`: Tailwind CSS entry point
- `resources/js/app.js`: Alpine.js and JavaScript modules
- `public/css/`: Additional stylesheets for tree visualization

### Configuration
- Multi-environment support with testing database in `phpunit.xml`
- Strict PHP coding standards in `pint.json`
- Vite configuration with Livewire hot reloading

## Important Notes

- The application uses MySQL Recursive CTEs requiring MySQL 8.0.1+ or MariaDB 10.2.2+
- All PHP files use strict types declaration
- Activity logging is enabled for audit trails on critical models
- Team-based permissions control access to genealogical data
- The codebase follows Laravel conventions with PSR-4 autoloading

## Tech Lead Tony Session Management

### Session Continuity
- **Tony sessions**: Start with `/engage` for context recovery
- **Session handoffs**: Zero data loss via scratchpad system
- **Agent coordination**: Monitor via logs/coordination/coordination-status.log

### Universal Deployment
- **Auto-setup**: Natural language triggers deploy Tony infrastructure
- **Session types**: New deployment vs. continuation via `/engage`
- **Context efficiency**: Tony setup isolated from regular agent sessions

---

**Tony Infrastructure**: ✅ Deployed via universal auto-setup system
**Session Continuity**: ✅ /engage command available for handoffs