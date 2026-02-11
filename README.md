# Ecobin: Smart Waste Management System

EcoBin is a smart waste management system that uses IoT technology to improve how waste is collected. The system uses sensors installed inside waste bins to monitor the bin fill level in real time. When a bin is nearly full, the system automatically sends alerts and automatically assigns collection tasks through a web-based management platform. The platform also helps manage cleaner attendance, task assignments, maintenance reports, inventory tracking, and performance monitoring. 


## Admin's Dashboard
<img width="1904" height="944" alt="image" src="https://github.com/user-attachments/assets/90645a1a-f15f-4162-9a3b-09dfe4c37389" />


## Cleaner's Dashboard
<img width="241" height="518" alt="image" src="https://github.com/user-attachments/assets/b498c604-147e-4bcf-aa2d-4b3212cac967" />
<img width="240" height="518" alt="image" src="https://github.com/user-attachments/assets/a72be931-cfdd-405d-b62e-8526f4bc93b9" />

## EcoBin's SmartBin
![WhatsApp Image 2026-02-06 at 15 01 40](https://github.com/user-attachments/assets/dcf5077e-79a7-46a3-8bbd-cda2b8cdceda)
![WhatsApp Image 2026-02-06 at 15 02 32](https://github.com/user-attachments/assets/37879327-bd7b-41e5-9c1a-2866c3663519)
![WhatsApp Image 2026-02-11 at 13 48 41](https://github.com/user-attachments/assets/8a0831cd-18c6-4e42-866e-1efb730c3fc9)




# Key Features
1. **Real-Time Bin Monitoring** - Track fill levels, weight, and GPS location via IoT sensors
2. **Automated Task Assignment** - System creates collection tasks when bins reach 80% capacity
3. **Attendance System** - Clock-in/out system with GPS verification
4. **Employee Performance** Tracker - Comprehensive employee performance analytics
5. **Waste Collection Analytics** - Generate insights on collection patterns and waste trends
6. **Inventory Management** - Track cleaning supplies and automate restock requests
7. **Leave Management** - Digital leave application and approval workflow
8. **Maintenance & Issue Reporting** - Submit and track facility maintenance issues

# IoT Components
1. **HC-SR04 Ultrasonic Sensor Module** - Measures the fill level of the bin
2. **Battery holder 18650 2-slot** - Holds the 18650 batteries to provide power to the system.
3. **Battery Li-ion 18650 3.7V 2000mAH** - Rechargeable batteries that power the entire EcoBin system.
4. **Servo Motor TS90A** - Controls the lid of the bin.
5. **Base Board For ESP32 DevKit V1** - An expansion platform to easily connect other components to the ESP32 microcontroller.
6. **ESP32 DevKit V1** - The main microcontroller that processes sensor data, controls the servo motor, connects to Wi-Fi, and   sends bin status to the EcoBin website.
7. **MH Infrared Obstacle Sensor Module** - Used to trigger the servo to open the lid.
8. **GY-NEO6MV2 GPS Network Module** - Provides real-time GPS location of the bin,
9. **Jumper wires** - To connect all electronic components together on the board
10. **Hx711 load cell amplifier module** - Amplifies and converts the analog signal from the weight sensor into a digital signal that can be read by the ESP32 microcontroller for accurate waste weight measurement.
11. **Body weight sensor 50kg** - Detects the weight of the waste inside the bin by measuring the force applied on the sensor platform, providing data for weight-based waste analysis in the EcoBin system.

# Technology Stack
**Frontend:**
- HTML5
- CSS3
- Javascript
- Responsive design for mobile access

**Backend:**
- PHP
- MySQL
- Apache Web Server

**Development Tools**
- Visual Studio Code
- Arduino IDE (for ESP32 programming)
- XAMPP
- GitHub

## System Module
### For Administrators
1. **User Account Management** - Create and manage employee accounts
2. **Bin Monitoring** - Real-time dashboard monitoring of all bins
3. **Task Management** - Assign and track collection tasks
4. **Attendance System** - View employee attendance logs
5. **Performance Tracker** - Monitor cleaner performance
6. **Waste Analytics** - Generate reports and insights
7. **Inventory Management** - Manage cleaning supplies
8. **Leave Management** - Approve/reject leave requests
9. **Maintenance Reports** - Handle facility issues

### For Cleaners
1. **Dashboard** - View Brief statistics and alerts.
2. **Task Management** - View assigned tasks,Complete and report collections
3. **Attendance** - Clock in/out with GPS
4. **Performance** - View personal performance metrics
5. **Inventory** - Take supplies and request restocks
6. **Leave Requests** - Apply for leave digitally
7. **Maintenance Reports** - Report bin or facility issues

## Author
**Cyril Leopold Yong Tse Young**  
Network Engineering Student, Universiti Malaysia Sabah  




