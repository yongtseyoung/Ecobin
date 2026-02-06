# Ecobin: Smart Waste Management System

Admin's Dashboard
<img width="1904" height="944" alt="image" src="https://github.com/user-attachments/assets/90645a1a-f15f-4162-9a3b-09dfe4c37389" />

Cleaner's Dashboard

<img width="241" height="518" alt="image" src="https://github.com/user-attachments/assets/b498c604-147e-4bcf-aa2d-4b3212cac967" />
<img width="240" height="518" alt="image" src="https://github.com/user-attachments/assets/a72be931-cfdd-405d-b62e-8526f4bc93b9" />

EcoBin's SmartBin
![WhatsApp Image 2026-02-06 at 15 01 40](https://github.com/user-attachments/assets/dcf5077e-79a7-46a3-8bbd-cda2b8cdceda)



# Features
- User Account Management - Manage Users accounts 
- Bin Monitoring - Track fill levels, weight, and location via IoT sensors
- Attendance System - Clock-in/out system with GPS verification
- Task Management - Manage assigned tasks.
- Employee Performance Tracker - Comprehensive employee performance analytics
- Waste Collection Analytics - Generate insights on collection patterns and waste trends
- Inventory Management - Track cleaning supplies and automate restock requests
- Leave Management - Digital leave application and approval workflow
- Maintenance & Issue Reporting - Submit and track facility maintenance issues

# IoT Components
- HC-SR04 Ultrasonic Sensor Module - Measures the fill level of the bin
- Battery holder 18650 2-slot - Holds the 18650 batteries to provide power to the system.
- Battery Li-ion 18650 3.7V 2000mAH - Rechargeable batteries that power the entire EcoBin system.
- Servo Motor TS90A - Controls the lid of the bin.
- Base Board For ESP32 DevKit V1 - An expansion platform to easily connect other components to the ESP32 microcontroller.
- ESP32 DevKit V1 - The main microcontroller that processes sensor data, controls the servo motor, connects to Wi-Fi, and   sends bin status to the EcoBin website.
- MH Infrared Obstacle Sensor Module - Used to trigger the servo to open the lid.
- GY-NEO6MV2 GPS Network Module - Provides real-time GPS location of the bin,
- Jumper wires - To connect all electronic components together on the board
- hx711 load cell amplifier module - Amplifies and converts the analog signal from the weight sensor into a digital signal that can be read by the ESP32 microcontroller for accurate waste weight measurement.
- Body weight sensor 50kg - Detects the weight of the waste inside the bin by measuring the force applied on the sensor platform, providing data for weight-based waste analysis in the EcoBin system.












