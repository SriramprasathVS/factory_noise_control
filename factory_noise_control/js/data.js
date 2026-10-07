/**
 * Factory Acoustic Monitoring and Material Compliance System
 * Mock Telemetry & Compliance Data Store
 * 
 * Complies with ISO 1996 (Acoustics - Environmental Noise Description)
 * and OSHA standard 1910.95 (Occupational Noise Exposure).
 */

const FACTORY_ZONES = [
  {
    id: 1,
    code: "ZN-STAMP-01",
    name: "Heavy Stamping & Press Bay",
    area: "Building A - Floor 1",
    maxLimitDb: 85.0,
    dampenerType: "Resonant Cavity & Elastomer Pads",
    healthScore: 68
  },
  {
    id: 2,
    code: "ZN-CNC-02",
    name: "Precision CNC Milling Floor",
    area: "Building A - Floor 2",
    maxLimitDb: 80.0,
    dampenerType: "Acoustic Perforated Baffles",
    healthScore: 92
  },
  {
    id: 3,
    code: "ZN-TURB-03",
    name: "Turbine & Auxiliary Generator Room",
    area: "Powerhouse Utility Sub-Level",
    maxLimitDb: 85.0,
    dampenerType: "Composite Rockwool Sound Barriers",
    healthScore: 54
  },
  {
    id: 4,
    code: "ZN-ASSY-04",
    name: "Robotic Chassis Assembly Line",
    area: "Building B - Bay 1",
    maxLimitDb: 75.0,
    dampenerType: "Micro-Perforated Absorber Panels",
    healthScore: 96
  },
  {
    id: 5,
    code: "ZN-PAINT-05",
    name: "High-Velocity Paint & Coating Booth",
    area: "Building B - Bay 3",
    maxLimitDb: 75.0,
    dampenerType: "Silenced Vent Hoods & Polyurethane Foam",
    healthScore: 89
  },
  {
    id: 6,
    code: "ZN-MET-06",
    name: "Metallurgical Testing & QC Lab",
    area: "Quality Assurance Wing",
    maxLimitDb: 65.0,
    dampenerType: "Double-Glazed Acoustic Partitioning",
    healthScore: 98
  }
];

const INITIAL_ACOUSTIC_LOGS = [
  {
    id: 101,
    sensorTag: "MIC-STAMP-01A",
    zoneId: 1,
    zoneName: "Heavy Stamping & Press Bay",
    decibels: 88.6,
    frequencyHz: 120,
    materialStatus: "Elastomer Wear: High (82%)",
    complianceStatus: "CRITICAL",
    timestamp: "2026-10-05 09:32:14"
  },
  {
    id: 102,
    sensorTag: "MIC-STAMP-01B",
    zoneId: 1,
    zoneName: "Heavy Stamping & Press Bay",
    decibels: 86.2,
    frequencyHz: 145,
    materialStatus: "Cavity Resonance Misalignment",
    complianceStatus: "CRITICAL",
    timestamp: "2026-10-05 09:34:02"
  },
  {
    id: 103,
    sensorTag: "MIC-CNC-02A",
    zoneId: 2,
    zoneName: "Precision CNC Milling Floor",
    decibels: 76.4,
    frequencyHz: 420,
    materialStatus: "Baffle Absorption Nominal",
    complianceStatus: "COMPLIANT",
    timestamp: "2026-10-05 09:35:10"
  },
  {
    id: 104,
    sensorTag: "MIC-CNC-02B",
    zoneId: 2,
    zoneName: "Precision CNC Milling Floor",
    decibels: 81.2,
    frequencyHz: 380,
    materialStatus: "Spindle Acoustic Leakage",
    complianceStatus: "ELEVATED",
    timestamp: "2026-10-05 09:36:44"
  },
  {
    id: 105,
    sensorTag: "MIC-TURB-03A",
    zoneId: 3,
    zoneName: "Turbine & Auxiliary Generator Room",
    decibels: 92.4,
    frequencyHz: 60,
    materialStatus: "Rockwool Barrier Delamination",
    complianceStatus: "CRITICAL",
    timestamp: "2026-10-05 09:37:05"
  },
  {
    id: 106,
    sensorTag: "MIC-TURB-03B",
    zoneId: 3,
    zoneName: "Turbine & Auxiliary Generator Room",
    decibels: 89.1,
    frequencyHz: 180,
    materialStatus: "Structural Flanking Transmission",
    complianceStatus: "CRITICAL",
    timestamp: "2026-10-05 09:37:55"
  },
  {
    id: 107,
    sensorTag: "MIC-ASSY-04A",
    zoneId: 4,
    zoneName: "Robotic Chassis Assembly Line",
    decibels: 68.7,
    frequencyHz: 250,
    materialStatus: "Absorber Panels Pristine",
    complianceStatus: "COMPLIANT",
    timestamp: "2026-10-05 09:38:12"
  },
  {
    id: 108,
    sensorTag: "MIC-PAINT-05A",
    zoneId: 5,
    zoneName: "High-Velocity Paint & Coating Booth",
    decibels: 73.1,
    frequencyHz: 820,
    materialStatus: "Foam Attenuation Nominal",
    complianceStatus: "COMPLIANT",
    timestamp: "2026-10-05 09:38:50"
  },
  {
    id: 109,
    sensorTag: "MIC-MET-06A",
    zoneId: 6,
    zoneName: "Metallurgical Testing & QC Lab",
    decibels: 54.3,
    frequencyHz: 500,
    materialStatus: "Double-Glazing Fully Sealed",
    complianceStatus: "COMPLIANT",
    timestamp: "2026-10-05 09:39:15"
  }
];

const INITIAL_MAINTENANCE_REQUESTS = [
  {
    id: 501,
    zoneId: 3,
    equipmentTag: "TURB-GEN-01",
    priority: "EMERGENCY",
    issueDescription: "Severe 60Hz hum exceeding 92 dB; rockwool acoustic blanket detached from turbine enclosure.",
    reportedBy: "S. Vance (Acoustic Safety Lead)",
    status: "IN_PROGRESS",
    createdAt: "2026-10-05 08:45:00"
  },
  {
    id: 502,
    zoneId: 1,
    equipmentTag: "STAMP-HYD-04",
    priority: "HIGH",
    issueDescription: "Hydraulic impact damper compression failure causing repeated peak readings > 88 dB.",
    reportedBy: "M. Gallagher (Shift Engineer)",
    status: "PENDING",
    createdAt: "2026-10-05 09:10:15"
  }
];

// Export to window for vanilla browser usage
window.AcousticData = {
  zones: FACTORY_ZONES,
  logs: INITIAL_ACOUSTIC_LOGS,
  maintenance: INITIAL_MAINTENANCE_REQUESTS
};
