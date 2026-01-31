#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <ESP32Servo.h>
#include <TinyGPS++.h>
#include <HardwareSerial.h>
#include "HX711.h"

const char* ssid = "Carti";
const char* password = "limaringgit";

const char* serverUrl = "http://172.20.10.4/ecobin/api/iot_receiver.php";
const char* binCode = "BIN-ESP-32";

const int trigPin = 13;
const int echoPin = 12;
const int irPin = 4;
const int servoPin = 14;

const int LOADCELL_DOUT_PIN = 18;
const int LOADCELL_SCK_PIN = 19;

HardwareSerial GPS_Serial(2);
TinyGPSPlus gps;

Servo lidServo;
HX711 scale;

const float binHeight = 24;

float fillLevel = 0.0;
float weightKG = 0.0;
float batteryLevel = 100.0;

bool lidOpen = false;
bool lastIrState = HIGH;

String deviceMAC = "";
int wifiSignalStrength = 0;

unsigned long lastMeasureTime = 0;
unsigned long lastGPSPrint = 0;
unsigned long lastWeightRead = 0;
unsigned long lastServerSend = 0;
unsigned long startTime = 0;

const unsigned long fillInterval = 5000;
const unsigned long gpsInterval = 5000;
const unsigned long weightInterval = 5000;
const unsigned long serverInterval = 5000;

float calibrationFactor = 12050;

void connectWiFi();
void sendDataToServer();
String getLidStatus();
void estimateBattery();

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n\n================================");
  Serial.println("   EcoBin IoT System Starting   ");
  Serial.println("================================\n");

  deviceMAC = WiFi.macAddress();
  Serial.print("Device MAC Address: ");
  Serial.println(deviceMAC);

  pinMode(trigPin, OUTPUT);
  pinMode(echoPin, INPUT);
  pinMode(irPin, INPUT);

  lidServo.attach(servoPin);
  lidServo.write(0);
  Serial.println("✓ Servo motor initialized (lid closed)");

  GPS_Serial.begin(9600, SERIAL_8N1, 16, 17);
  Serial.println("✓ GPS module initialized");

  scale.begin(LOADCELL_DOUT_PIN, LOADCELL_SCK_PIN);
  scale.set_scale(calibrationFactor);
  scale.tare();
  Serial.println("✓ Weight sensor initialized (taring complete)");

  connectWiFi();
  
  startTime = millis();
  Serial.println("\n✓ System ready!\n");
}

void loop() {
  unsigned long currentMillis = millis();

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠ WiFi disconnected! Reconnecting...");
    connectWiFi();
  }

  wifiSignalStrength = WiFi.RSSI();

  while (GPS_Serial.available()) {
    gps.encode(GPS_Serial.read());
  }

  if (currentMillis - lastGPSPrint >= gpsInterval) {
    lastGPSPrint = currentMillis;

    if (gps.location.isValid()) {
      Serial.print(" GPS → LAT: ");
      Serial.print(gps.location.lat(), 6);
      Serial.print(" | LON: ");
      Serial.print(gps.location.lng(), 6);
      Serial.print(" | Satellites: ");
      Serial.println(gps.satellites.value());
    } else {
      Serial.println(" GPS → Searching for satellites...");
    }
  }

  int irState = digitalRead(irPin);
  if (irState == LOW && lastIrState == HIGH) {
    lidOpen = !lidOpen;

    if (lidOpen) {
      Serial.println(" IR → Opening lid...");
      lidServo.write(0);
    } else {
      Serial.println(" IR → Closing lid...");
      lidServo.write(90);
    }
  }
  lastIrState = irState;

  if (currentMillis - lastMeasureTime >= fillInterval) {
    lastMeasureTime = currentMillis;

    digitalWrite(trigPin, LOW);
    delayMicroseconds(2);
    digitalWrite(trigPin, HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPin, LOW);

    long duration = pulseIn(echoPin, HIGH, 30000);
    float distance = duration * 0.034 / 2;

    if (distance <= 5.0) {
      fillLevel = 100.0;
    } else {
      float sensorOffset = 2.0;
      float effectiveHeight = binHeight - sensorOffset;

      if (effectiveHeight <= 0) {
        Serial.println(" ERROR: binHeight must be > 2 cm!");
        fillLevel = 0;
      } else {
        float adjustedDistance = constrain(distance - sensorOffset, 0, effectiveHeight);
        fillLevel = 100.0 - (adjustedDistance / effectiveHeight * 100.0);
        fillLevel = constrain(fillLevel, 0, 100);
      }
    }

    Serial.print(" Ultrasonic → Distance: ");
    Serial.print(distance, 1);
    Serial.print(" cm | Fill Level: ");
    Serial.print(fillLevel, 1);
    Serial.println(" %");
  }

  if (currentMillis - lastWeightRead >= weightInterval) {
    lastWeightRead = currentMillis;

    if (scale.is_ready()) {
      weightKG = scale.get_units(5);

      Serial.print("  Weight → ");
      Serial.print(weightKG, 2);
      Serial.println(" kg");
    } else {
      Serial.println("  Weight Sensor → ERROR: Not ready");
    }
  }

  if (currentMillis - lastServerSend >= serverInterval) {
    lastServerSend = currentMillis;
    
    estimateBattery();
    sendDataToServer();
  }
}

void connectWiFi() {
  Serial.print("📡 Connecting to WiFi: ");
  Serial.println(ssid);
  
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi Connected!");
    Serial.print("   IP Address: ");
    Serial.println(WiFi.localIP());
    Serial.print("   Signal Strength: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    Serial.println("\n WiFi Connection Failed!");
    Serial.println("   System will continue without server connection.");
  }
}

void sendDataToServer() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠ Cannot send data - WiFi not connected");
    return;
  }

  Serial.println("\n Sending data to server...");
  
  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<512> doc;
  
  doc["device_id"] = deviceMAC;
  doc["bin_code"] = binCode;
  doc["fill_level"] = round(fillLevel * 100) / 100.0;
  doc["weight"] = round(weightKG * 100) / 100.0;
  doc["distance"] = 0;
  doc["battery_level"] = round(batteryLevel * 100) / 100.0;
  doc["lid_status"] = getLidStatus();
  doc["signal_strength"] = wifiSignalStrength;

  if (gps.location.isValid()) {
    doc["gps_lat"] = gps.location.lat();
    doc["gps_lng"] = gps.location.lng();
  } else {
    doc["gps_lat"] = nullptr;
    doc["gps_lng"] = nullptr;
  }

  String jsonPayload;
  serializeJson(doc, jsonPayload);

  Serial.println("   JSON Payload:");
  Serial.println("   " + jsonPayload);

  int httpResponseCode = http.POST(jsonPayload);

  if (httpResponseCode > 0) {
    Serial.print("   ✓ Server Response Code: ");
    Serial.println(httpResponseCode);
    
    String response = http.getString();
    Serial.println("   Server Response:");
    Serial.println("   " + response);
  } else {
    Serial.print("   HTTP Error: ");
    Serial.println(http.errorToString(httpResponseCode));
  }

  http.end();
  Serial.println();
}

String getLidStatus() {
  return lidOpen ? "open" : "closed";
}

void estimateBattery() {
  unsigned long runtime = millis() - startTime;
  float hoursRunning = runtime / 3600000.0;
  
  batteryLevel = 100.0 - (hoursRunning * 4.0);
  
  batteryLevel = constrain(batteryLevel, 0, 100);
}