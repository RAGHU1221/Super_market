; Inno Setup script for Supermarket POS (Windows desktop)
; Built automatically by GitHub Actions (.github/workflows/build_windows.yml)
; Produces installer\Output\SupermarketPOS-Setup.exe

#define MyAppName "Supermarket POS"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Supermarket Suite"
#define MyAppExeName "supermarket_pos.exe"

[Setup]
AppId={{8F2B7B7E-4B7B-4B1B-9B0A-2A1E5C6D9F10}}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
OutputDir=Output
OutputBaseFilename=SupermarketPOS-Setup
Compression=lzma
SolidCompression=yes
WizardStyle=modern
DisableProgramGroupPage=yes
ArchitecturesInstallIn64BitMode=x64

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Additional shortcuts:"

[Files]
Source: "..\build\windows\x64\runner\Release\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Launch {#MyAppName}"; Flags: nowait postinstall skipifsilent
