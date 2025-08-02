unit APIService;

interface

uses
  System.SysUtils, System.Classes, System.Net.HttpClient, System.Json;

type
  TAPIService = class
  private
    FBaseURL: string;
    FAPIKey: string;
    FClient: TNetHTTPClient;
  public
    constructor Create(const aBaseURL, aAPIKey: string);
    destructor Destroy; override;

    function GetConfig: string; // Returns JSON string
    function UpdateConfig(const aConfigJSON: string): boolean;
    function StartBackup: boolean;
  end;

implementation

{ TAPIService }

constructor TAPIService.Create(const aBaseURL, aAPIKey: string);
begin
  inherited Create;
  FBaseURL := IncludeTrailingPathDelimiter(aBaseURL) + 'api/duplicator/';
  FAPIKey := aAPIKey;
  FClient := TNetHTTPClient.Create(nil);
  FClient.CustomHeaders['X-API-Key'] := FAPIKey;
  FClient.ContentType := 'application/json';
end;

destructor TAPIService.Destroy;
begin
  FClient.Free;
  inherited Destroy;
end;

function TAPIService.GetConfig: string;
var
  Response: IHTTPResponse;
begin
  Response := FClient.Get(FBaseURL + 'config');
  if Response.StatusCode = 200 then
  begin
    Result := Response.ContentAsString;
  end
  else
  begin
    raise Exception.CreateFmt('API Error: %d - %s', [Response.StatusCode, Response.StatusText]);
  end;
end;

function TAPIService.UpdateConfig(const aConfigJSON: string): boolean;
var
  Response: IHTTPResponse;
  JsonToSend: TStringStream;
begin
  JsonToSend := TStringStream.Create(aConfigJSON, TEncoding.UTF8);
  try
    Response := FClient.Post(FBaseURL + 'config', JsonToSend);
    Result := (Response.StatusCode = 200);
    if not Result then
    begin
       raise Exception.CreateFmt('API Error: %d - %s', [Response.StatusCode, Response.StatusText]);
    end;
  finally
    JsonToSend.Free;
  end;
end;

function TAPIService.StartBackup: boolean;
var
  Response: IHTTPResponse;
begin
  Response := FClient.Post(FBaseURL + 'backup', TStream(nil));
  Result := (Response.StatusCode = 200);
  if not Result then
  begin
     raise Exception.CreateFmt('API Error: %d - %s', [Response.StatusCode, Response.StatusText]);
  end;
end;

end.
