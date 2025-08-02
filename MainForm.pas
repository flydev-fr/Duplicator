unit MainForm;

interface

uses
  System.SysUtils, System.Types, System.UITypes, System.Classes, System.Variants,
  System.Json, System.Net.HttpClient, // Added for API communication
  FMX.Types, FMX.Controls, FMX.Forms, FMX.Graphics, FMX.Dialogs,
  FMX.Layouts, FMX.StdCtrls, FMX.Controls.Presentation, FMX.Edit,
  FMX.Memo.Types, FMX.ScrollBox, FMX.Memo,
  APIService; // Include the new API service unit

type
  TForm1 = class(TForm)
    Layout: TLayout;
    grpAPI: TGroupBox;
    lblServerURL: TLabel;
    editServerURL: TEdit;
    lblAPIKey: TLabel;
    editAPIKey: TEdit;
    btnFetchConfig: TButton;
    btnSaveConfig: TButton;
    grpPackage: TGroupBox;
    lblPackageName: TLabel;
    editPackageName: TEdit;
    grpExclusions: TGroupBox;
    memoExclusions: TMemo;
    grpLogs: TGroupBox;
    memoLogs: TMemo;
    btnCreateBackup: TButton;
    procedure btnCreateBackupClick(Sender: TObject);
    procedure btnFetchConfigClick(Sender: TObject);
    procedure btnSaveConfigClick(Sender: TObject);
    procedure FormCreate(Sender: TObject);
    procedure FormDestroy(Sender: TObject);
  private
    { Private declarations }
    FAPIService: TAPIService;
    procedure InitAPIService;
  public
    { Public declarations }
  end;

var
  Form1: TForm1;

implementation

{$R *.fmx}

{ TForm1 }

procedure TForm1.FormCreate(Sender: TObject);
begin
  FAPIService := nil;
end;

procedure TForm1.FormDestroy(Sender: TObject);
begin
  FAPIService.Free;
end;

procedure TForm1.InitAPIService;
begin
  if Assigned(FAPIService) then
    FAPIService.Free;

  memoLogs.Lines.Add('Initializing API service for ' + editServerURL.Text);
  FAPIService := TAPIService.Create(editServerURL.Text, editAPIKey.Text);
end;

procedure TForm1.btnFetchConfigClick(Sender: TObject);
var
  ConfigJSON, JsonStr: string;
  JSONObject, JsonValue: TJSONValue;
begin
  try
    InitAPIService;
    memoLogs.Lines.Add('Fetching configuration from server...');
    ConfigJSON := FAPIService.GetConfig;

    JSONObject := TJSONObject.ParseJSONValue(ConfigJSON);
    try
      // Populate fields from JSON
      if JSONObject.TryGetValue('packageName', JsonValue) then
        editPackageName.Text := JsonValue.Value;

      if JSONObject.TryGetValue('ignoredPath', JsonValue) then
        memoExclusions.Lines.Text := JsonValue.Value.Replace(#13#10, sLineBreak);

      memoLogs.Lines.Add('[SUCCESS] Configuration loaded successfully.');
    finally
      JSONObject.Free;
    end;
  except
    on E: Exception do
      memoLogs.Lines.Add('[ERROR] ' + E.Message);
  end;
end;

procedure TForm1.btnSaveConfigClick(Sender: TObject);
var
  ConfigObject: TJSONObject;
  JsonToSend: string;
begin
  try
    InitAPIService;
    memoLogs.Lines.Add('Saving configuration to server...');

    ConfigObject := TJSONObject.Create;
    try
      ConfigObject.AddPair('packageName', TJSONString.Create(editPackageName.Text));
      ConfigObject.AddPair('ignoredPath', TJSONString.Create(memoExclusions.Lines.Text));
      // Add other config fields here as they are added to the UI

      JsonToSend := ConfigObject.ToString;
    finally
      ConfigObject.Free;
    end;

    if FAPIService.UpdateConfig(JsonToSend) then
      memoLogs.Lines.Add('[SUCCESS] Configuration saved successfully.')
    else
      memoLogs.Lines.Add('[ERROR] Failed to save configuration.');

  except
    on E: Exception do
      memoLogs.Lines.Add('[ERROR] ' + E.Message);
  end;
end;

procedure TForm1.btnCreateBackupClick(Sender: TObject);
begin
  try
    InitAPIService;
    memoLogs.Lines.Clear;
    memoLogs.Lines.Add('Sending backup request to server...');
    if FAPIService.StartBackup then
      memoLogs.Lines.Add('[SUCCESS] Backup process initiated on server.')
    else
      memoLogs.Lines.Add('[ERROR] Failed to initiate backup on server.');
  except
    on E: Exception do
      memoLogs.Lines.Add('[ERROR] ' + E.Message);
  end;
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
