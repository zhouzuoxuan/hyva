# Lencarta_Core

Project-level foundation module for shared configuration and low-level infrastructure.

## What it gives you

- A shared **Lencarta** config tab in `Stores > Configuration`
- A global feature flag (`lencarta_core/general/enabled`)
- A debug logging switch (`lencarta_core/general/debug_logging`)
- A shared brand name config value
- A reusable config model: `Lencarta\Core\Model\Config`
- A reusable Hyvä / frontend-friendly ViewModel: `Lencarta\Core\ViewModel\StoreConfig`
- A dedicated log file: `var/log/lencarta_core.log`
