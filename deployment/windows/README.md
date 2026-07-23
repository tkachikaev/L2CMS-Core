# KaevCMS on Windows

All Windows PowerShell tooling is stored in this directory and runs directly from here. Scripts determine the project root automatically; do not copy them back to the root.

```powershell
.\deployment\windows\setup.ps1
.\deployment\windows\serve.ps1
.\deployment\windows\doctor.ps1
.\deployment\windows\quality.ps1
.\deployment\windows\browser-setup.ps1
.\deployment\windows\browser-quality.ps1
.\deployment\windows\security-audit.ps1
.\deployment\windows\build-shared-hosting-package.ps1
```

For an update, extract the patch over the project and run the current apply script from this directory.

`build-shared-hosting-package.ps1` создаёт безопасный hosting ZIP с закрытым ядром и отдельной публичной папкой для панелей без настройки Document Root. Архив создаётся PHP-сборщиком с переносимыми путями `/`, а PowerShell проверяет SHA256 и структуру ZIP.
