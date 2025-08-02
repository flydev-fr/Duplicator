unit MainForm;

interface

uses
  System.SysUtils, System.Types, System.UITypes, System.Classes, System.Variants,
  System.IOUtils, System.Zip, System.Generics.Collections, Winapi.Windows, // Added for file/db operations and generics
  FMX.Types, FMX.Controls, FMX.Forms, FMX.Graphics, FMX.Dialogs,
  FMX.Layouts, FMX.StdCtrls, FMX.Controls.Presentation, FMX.Edit,
  // Assuming Skia for FireMonkey is installed and units are available
  // For example: Skia.FMX.Labels, Skia.FMX.Memos, etc.
  // Using standard components for this example, which can be replaced by Skia counterparts.
  FMX.Memo.Types, FMX.ScrollBox, FMX.Memo;

type
  TForm1 = class(TForm)
    Layout: TLayout;
    lblSourceDir: TLabel;
    editSourceDir: TEdit;
    btnSelectSource: TButton;
    lblDestinationPath: TLabel;
    editDestinationPath: TEdit;
    btnSelectDestination: TButton;
    grpDatabase: TGroupBox;
    lblDBHost: TLabel;
    editDBHost: TEdit;
    lblDBName: TLabel;
    editDBName: TEdit;
    lblDBUser: TLabel;
    editDBUser: TEdit;
    lblDBPass: TLabel;
    editDBPass: TEdit;
    grpExclusions: TGroupBox;
    memoExclusions: TMemo;
    grpLogs: TGroupBox;
    memoLogs: TMemo;
    btnCreateBackup: TButton;
    procedure btnCreateBackupClick(Sender: TObject);
    procedure btnSelectSourceClick(Sender: TObject);
    procedure btnSelectDestinationClick(Sender: TObject);
  private
    { Private declarations }
    function IsExcluded(const aPath: string; const aExclusions: TStrings): Boolean;
    procedure ExecuteFileBackup(const aSourceDirectory, aDestinationZipFile: string; const aExclusions: TStrings);
    procedure ExecuteDatabaseDump(const aDBHost, aDBName, aDBUser, aDBPass, aDumpFilePath: string);
  public
    { Public declarations }
  end;

var
  Form1: TForm1;

implementation

{$R *.fmx}

{ TForm1 }

// This procedure is the main entry point for the backup process.
procedure TForm1.btnCreateBackupClick(Sender: TObject);
var
  TempSQLFile: string;
  ZipFile: TZipFile;
begin
  memoLogs.Lines.Clear;
  memoLogs.Lines.Add('Starting backup process...');

  // 1. Validate inputs
  if not TDirectory.Exists(editSourceDir.Text) then
  begin
    memoLogs.Lines.Add('[ERROR] Source directory does not exist: ' + editSourceDir.Text);
    Exit;
  end;

  if editDestinationPath.Text = '' then
  begin
    memoLogs.Lines.Add('[ERROR] Destination path cannot be empty.');
    Exit;
  end;

  // 2. Perform Database Dump
  TempSQLFile := TPath.GetTempFileName;
  try
    memoLogs.Lines.Add('Starting database dump...');
    ExecuteDatabaseDump(editDBHost.Text, editDBName.Text, editDBUser.Text, editDBPass.Text, TempSQLFile);
    memoLogs.Lines.Add('[SUCCESS] Database dump completed: ' + TempSQLFile);
  except
    on E: Exception do
    begin
      memoLogs.Lines.Add('[ERROR] An exception occurred during database dump: ' + E.Message);
      TFile.Delete(TempSQLFile);
      Exit;
    end;
  end;

  // 3. Perform File Backup
  try
    ExecuteFileBackup(editSourceDir.Text, editDestinationPath.Text, memoExclusions.Lines);
    memoLogs.Lines.Add('[SUCCESS] File backup completed successfully.');
  except
    on E: Exception do
    begin
      memoLogs.Lines.Add('[ERROR] An exception occurred during file backup: ' + E.Message);
      TFile.Delete(TempSQLFile);
      Exit;
    end;
  end;

  // 4. Add Database dump to the archive
  try
    memoLogs.Lines.Add('Adding database dump to the archive...');
    ZipFile := TZipFile.Create;
    try
      ZipFile.Open(editDestinationPath.Text, zmReadWrite);
      ZipFile.Add(TempSQLFile, 'database_dump.sql');
      memoLogs.Lines.Add('[SUCCESS] Database dump added to the archive.');
    finally
      ZipFile.Free;
    end;
  finally
    TFile.Delete(TempSQLFile);
    memoLogs.Lines.Add('Temporary SQL file deleted.');
  end;

  memoLogs.Lines.Add('Full backup process completed.');
end;

procedure TForm1.ExecuteDatabaseDump(const aDBHost, aDBName, aDBUser, aDBPass, aDumpFilePath: string);
var
  CmdLine: string;
  SI: TStartupInfo;
  PI: TProcessInformation;
  SA: TSecurityAttributes;
  hFile: THandle;
  CmdLinePtr: PChar;
begin
  // Basic validation
  if (aDBHost = '') or (aDBName = '') or (aDBUser = '') then
  begin
    raise Exception.Create('Database credentials cannot be empty.');
  end;

  // Construct the command line for mysqldump
  CmdLine := Format('mysqldump.exe -h %s -u %s --password=%s %s',
    [aDBHost, aDBUser, aDBPass, aDBName]);

  memoLogs.Lines.Add('Executing mysqldump...');

  // Set up security attributes to allow handle inheritance
  ZeroMemory(@SA, SizeOf(TSecurityAttributes));
  SA.nLength := SizeOf(TSecurityAttributes);
  SA.bInheritHandle := True;
  SA.lpSecurityDescriptor := nil;

  // Create the output file with security attributes
  hFile := CreateFile(PChar(aDumpFilePath), GENERIC_WRITE, FILE_SHARE_READ, @SA, CREATE_ALWAYS, FILE_ATTRIBUTE_NORMAL, 0);
  if hFile = INVALID_HANDLE_VALUE then
    RaiseLastOSError;

  try
    // Set up startup info to redirect standard output to our file handle
    ZeroMemory(@SI, SizeOf(TStartupInfo));
    SI.cb := SizeOf(TStartupInfo);
    SI.dwFlags := STARTF_USESTDHANDLES;
    SI.hStdInput := GetStdHandle(STD_INPUT_HANDLE);
    SI.hStdOutput := hFile;
    SI.hStdError := hFile; // Redirect errors to the same file

    // Create the process
    CmdLinePtr := PChar(CmdLine);
    if not CreateProcess(nil, CmdLinePtr, nil, nil, True, CREATE_NO_WINDOW, nil, nil, SI, PI) then
      RaiseLastOSError;

    try
      // Wait for the process to finish
      WaitForSingleObject(PI.hProcess, INFINITE);

      var ExitCode: DWord;
      GetExitCodeProcess(PI.hProcess, ExitCode);
      if ExitCode <> 0 then
      begin
        raise Exception.CreateFmt('mysqldump failed with exit code %d.', [ExitCode]);
      end;
    finally
      CloseHandle(PI.hProcess);
      CloseHandle(PI.hThread);
    end;
  finally
    CloseHandle(hFile);
  end;
end;

procedure TForm1.ExecuteFileBackup(const aSourceDirectory, aDestinationZipFile: string; const aExclusions: TStrings);
var
  ZipFile: TZipFile;
  FileNames: TStringDynArray;
  SubDirs: TStringDynArray;
  I: Integer;
  EntryName: string;
  BaseDir: string;
  DirStack: TStack<string>;
  CurrentRelativeDir: string;
begin
  memoLogs.Lines.Add('Creating ZIP file: ' + aDestinationZipFile);
  ZipFile := TZipFile.Create;
  try
    ZipFile.Open(aDestinationZipFile, zmWrite);

    BaseDir := IncludeTrailingPathDelimiter(aSourceDirectory);

    // Use a stack for iterative traversal instead of recursion to avoid stack overflow on deep directories
    DirStack := TStack<string>.Create;
    DirStack.Push(''); // Start with the root relative path

    while DirStack.Count > 0 do
    begin
      CurrentRelativeDir := DirStack.Pop;
      var CurrentFullDir := TPath.Combine(BaseDir, CurrentRelativeDir);

      // Add files in the current directory
      FileNames := TDirectory.GetFiles(CurrentFullDir);
      for I := 0 to High(FileNames) do
      begin
        EntryName := TPath.Combine(CurrentRelativeDir, TPath.GetFileName(FileNames[I]));
        if not IsExcluded(EntryName, aExclusions) then
        begin
          ZipFile.Add(FileNames[I], EntryName);
          memoLogs.Lines.Add('Adding file: ' + EntryName);
        end
        else
        begin
          memoLogs.Lines.Add('Excluding file: ' + EntryName);
        end;
      end;

      // Add subdirectories to the stack
      SubDirs := TDirectory.GetDirectories(CurrentFullDir);
      for I := 0 to High(SubDirs) do
      begin
        EntryName := TPath.Combine(CurrentRelativeDir, TPath.GetFileName(SubDirs[I]));
        if not IsExcluded(EntryName, aExclusions) then
        begin
          DirStack.Push(EntryName);
        end
        else
        begin
          memoLogs.Lines.Add('Excluding directory: ' + EntryName);
        end;
      end;
    end;

  finally
    ZipFile.Free;
  end;
end;

function TForm1.IsExcluded(const aPath: string; const aExclusions: TStrings): Boolean;
var
  I: Integer;
  Exclusion, Path, Ext: string;
begin
  Result := False;
  Path := aPath.ToLower;
  Ext := TPath.GetExtension(Path);

  for I := 0 to aExclusions.Count - 1 do
  begin
    Exclusion := Trim(aExclusions[I]).ToLower;
    if Exclusion = '' then Continue;

    // 1. Check for wildcard extension (e.g., '*.zip')
    if (Exclusion.Chars[0] = '*') and (SameText(Ext, Copy(Exclusion, 2, MaxInt))) then
    begin
      Result := True;
      Exit;
    end;

    // 2. Check for directory containment (e.g., '/cache/' or 'cache\')
    // Ensures we match a full directory name
    if (Exclusion.EndsWith('\')) or (Exclusion.EndsWith('/')) then
    begin
      if Path.Contains(Exclusion) then
      begin
        Result := True;
        Exit;
      end;
    end;

    // 3. Check for exact file/folder name match
    if SameText(TPath.GetFileName(Path), Exclusion) then
    begin
      Result := True;
      Exit;
    end;
  end;
end;

// These procedures open file/folder selection dialogs
// to make it easier for the user to select paths.
procedure TForm1.btnSelectDestinationClick(Sender: TObject);
begin
  // Placeholder for a file save dialog to select the backup destination file.
  ShowMessage('Destination selection not yet implemented.');
end;

procedure TForm1.btnSelectSourceClick(Sender: TObject);
begin
  // Placeholder for a folder selection dialog.
  ShowMessage('Source selection not yet implemented.');
end;

end.
// FMX File Description (Normally in MainForm.fmx)
//
// object Form1: TForm1
//   Width = 640
//   Height = 600 // Increased height for new fields
//   object Layout: TLayout
//     Align = Client
//     object lblSourceDir: TLabel
//       Text = 'Source Website Directory:'
//     end
//     object editSourceDir: TEdit
//       Width = 300
//     end
//     object btnSelectSource: TButton
//       Text = '...'
//     end
//     object lblDestinationPath: TLabel
//       Text = 'Backup Destination Path:'
//     end
//     object editDestinationPath: TEdit
//       Width = 300
//     end
//     object btnSelectDestination: TButton
//       Text = '...'
//     end
//     object grpDatabase: TGroupBox
//       Text = 'Database Credentials'
//       object lblDBHost: TLabel
//         Text = 'Host:'
//       end
//       object editDBHost: TEdit
//       end
//       object lblDBName: TLabel
//         Text = 'Database:'
//       end
//       object editDBName: TEdit
//       end
//       object lblDBUser: TLabel
//         Text = 'User:'
//       end
//       object editDBUser: TEdit
//       end
//       object lblDBPass: TLabel
//         Text = 'Password:'
//       end
//       object editDBPass: TEdit
//         Password = True
//       end
//     end
//     object grpExclusions: TGroupBox
//       Text = 'Exclusions (one per line)'
//       object memoExclusions: TMemo
//         Align = Client
//       end
//     end
//     object grpLogs: TGroupBox
//       Text = 'Logs'
//       object memoLogs: TMemo
//         Align = Client
//         ReadOnly = True
//       end
//     end
//     object btnCreateBackup: TButton
//       Text = 'Create Backup'
//       Height = 40
//     end
//   end
// end
