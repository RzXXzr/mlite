package fhir

import (
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/google/uuid"
)

// GenUUID generates a new UUID v4.
func GenUUID() string {
	return uuid.New().String()
}

// ConvertTimeSatset converts local time to UTC ISO format for Satu Sehat.
func ConvertTimeSatset(waktu string, tzOffsetHours int) string {
	layouts := []string{
		"2006-01-02 15:04:05",
		"2006-01-02 15:04",
		"2006-01-02T15:04:05",
	}
	var t time.Time
	var err error
	for _, layout := range layouts {
		t, err = time.Parse(layout, waktu)
		if err == nil {
			break
		}
	}
	if err != nil {
		return waktu
	}
	t = t.Add(time.Duration(tzOffsetHours) * time.Hour)
	return t.Format("2006-01-02T15:04:05")
}

// M is a shorthand for map[string]interface{}.
type M = map[string]interface{}

// === Encounter ===

// EncounterParams holds parameters for building an Encounter resource.
type EncounterParams struct {
	NoRawat        string
	OrgID          string
	PatientIHS     string
	PatientName    string
	PractitionerID string
	PractitionerName string
	LocationID     string
	LocationName   string
	TglRegistrasi  string
	JamReg         string
	ClassCode      string // AMB or IMP
	ClassDisplay   string // ambulatory or inpatient encounter
	TimezoneOffset string // e.g. +07:00
}

// BuildEncounter builds a FHIR Encounter resource JSON.
func BuildEncounter(p EncounterParams) M {
	endTime := p.JamReg
	if t, err := time.Parse("15:04:05", p.JamReg); err == nil {
		endTime = t.Add(10 * time.Minute).Format("15:04:05")
	}

	return M{
		"resourceType": "Encounter",
		"status":       "arrived",
		"class": M{
			"system":  "http://terminology.hl7.org/CodeSystem/v3-ActCode",
			"code":    p.ClassCode,
			"display": p.ClassDisplay,
		},
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"participant": []M{
			{
				"type": []M{
					{
						"coding": []M{
							{
								"system":  "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
								"code":    "ATND",
								"display": "attender",
							},
						},
					},
				},
				"individual": M{
					"reference": "Practitioner/" + p.PractitionerID,
					"display":   p.PractitionerName,
				},
			},
		},
		"period": M{
			"start": p.TglRegistrasi + "T" + p.JamReg + p.TimezoneOffset,
		},
		"location": []M{
			{
				"location": M{
					"reference": "Location/" + p.LocationID,
					"display":   p.LocationName,
				},
			},
		},
		"statusHistory": []M{
			{
				"status": "arrived",
				"period": M{
					"start": p.TglRegistrasi + "T" + p.JamReg + p.TimezoneOffset,
					"end":   p.TglRegistrasi + "T" + endTime + p.TimezoneOffset,
				},
			},
		},
		"serviceProvider": M{
			"reference": "Organization/" + p.OrgID,
		},
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/encounter/" + p.OrgID,
				"value":  p.NoRawat,
			},
		},
	}
}

// === Condition ===

// ConditionParams holds parameters for building a Condition resource.
type ConditionParams struct {
	UUIDEncounter string
	UUIDCondition string
	Code          string
	Display       string
	PatientIHS    string
	PatientName   string
	EncounterDisplay string
}

// BuildCondition builds a FHIR Condition resource (for bundle).
func BuildCondition(p ConditionParams) M {
	return M{
		"resourceType": "Condition",
		"clinicalStatus": M{
			"coding": []M{
				{
					"system":  "http://terminology.hl7.org/CodeSystem/condition-clinical",
					"code":    "active",
					"display": "Active",
				},
			},
		},
		"category": []M{
			{
				"coding": []M{
					{
						"system":  "http://terminology.hl7.org/CodeSystem/condition-category",
						"code":    "encounter-diagnosis",
						"display": "Encounter Diagnosis",
					},
				},
			},
		},
		"code": M{
			"coding": []M{
				{
					"system":  "http://hl7.org/fhir/sid/icd-10",
					"code":    p.Code,
					"display": p.Display,
				},
			},
		},
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   p.EncounterDisplay,
		},
	}
}

// BuildConditionBundle wraps a Condition in a bundle entry.
func BuildConditionBundle(p ConditionParams) M {
	return M{
		"fullUrl":  "urn:uuid:" + p.UUIDCondition,
		"resource": BuildCondition(p),
		"request": M{
			"method": "POST",
			"url":    "Condition",
		},
	}
}

// === Observation ===

// ObservationParams holds parameters for vital signs observations.
type ObservationParams struct {
	UUIDEncounter  string
	UUIDObservation string
	PatientIHS     string
	PractitionerID string
	EffectiveTime  string
	TimezoneOffset string
}

// BuildObservationHeartRate builds a heart rate observation.
func BuildObservationHeartRate(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "8867-4", "display": "Heart rate"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "beats/minute",
			"system": "http://unitsofmeasure.org",
			"code":   "/min",
		},
	}
}

// BuildObservationRespiration builds a respiratory rate observation.
func BuildObservationRespiration(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "9279-1", "display": "Respiratory rate"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "breaths/minute",
			"system": "http://unitsofmeasure.org",
			"code":   "/min",
		},
	}
}

// BuildObservationTemperature builds a body temperature observation.
func BuildObservationTemperature(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "8310-5", "display": "Body temperature"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "C",
			"system": "http://unitsofmeasure.org",
			"code":   "Cel",
		},
	}
}

// BuildObservationSystolic builds a systolic blood pressure observation.
func BuildObservationSystolic(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "8480-6", "display": "Systolic blood pressure"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "mm[Hg]",
			"system": "http://unitsofmeasure.org",
			"code":   "mm[Hg]",
		},
	}
}

// BuildObservationDiastolic builds a diastolic blood pressure observation.
func BuildObservationDiastolic(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "8462-4", "display": "Diastolic blood pressure"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"bodySite": M{
			"coding": []M{
				{"system": "http://snomed.info/sct", "code": "368209003", "display": "Right arm"},
			},
		},
		"valueQuantity": M{
			"value":  value,
			"unit":   "mm[Hg]",
			"system": "http://unitsofmeasure.org",
			"code":   "mm[Hg]",
		},
	}
}

// BuildObservationBloodPressure builds a Blood Pressure Panel observation with systolic/diastolic components.
func BuildObservationBloodPressure(p ObservationParams, systolic, diastolic float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "35094-2", "display": "Blood pressure panel"},
			},
			"text": "Blood pressure systolic & diastolic",
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"component": []M{
			{
				"code": M{
					"coding": []M{
						{"system": "http://loinc.org", "code": "8480-6", "display": "Systolic blood pressure"},
					},
				},
				"valueQuantity": M{
					"value":  systolic,
					"unit":   "mmHg",
					"system": "http://unitsofmeasure.org",
					"code":   "mm[Hg]",
				},
			},
			{
				"code": M{
					"coding": []M{
						{"system": "http://loinc.org", "code": "8462-4", "display": "Diastolic blood pressure"},
					},
				},
				"valueQuantity": M{
					"value":  diastolic,
					"unit":   "mmHg",
					"system": "http://unitsofmeasure.org",
					"code":   "mm[Hg]",
				},
			},
		},
	}
}

// BuildObservationSpO2 builds an oxygen saturation observation.
func BuildObservationSpO2(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "59408-5", "display": "Oxygen saturation in Arterial blood by Pulse oximetry"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "%",
			"system": "http://unitsofmeasure.org",
			"code":   "%",
		},
	}
}

// BuildObservationGCS builds a Glasgow Coma Scale observation.
func BuildObservationGCS(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "9269-2", "display": "Glasgow coma score total"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "{score}",
			"system": "http://unitsofmeasure.org",
			"code":   "{score}",
		},
	}
}

// BuildObservationKesadaran builds an ACVPU consciousness level observation.
func BuildObservationKesadaran(p ObservationParams, kesadaranText, display string) M {
	// Map kesadaran text to code
	codeMap := map[string]string{
		"Compos Mentis":     "248234008",
		"composmentis":      "248234008",
		"Somnolence":        "271782001",
		"somnolen":          "271782001",
		"Sopor":             "130987000",
		"sopor":             "130987000",
		"Coma":              "371632003",
		"koma":              "371632003",
		"Alert":             "248234008",
		"Confusion":         "130987000",
		"Voice":             "300202002",
		"Pain":              "450834008",
		"Unresponsive":      "422768004",
		"Apatis":            "271782001",
	}
	code := "248234008" // default: Mentally alert
	if c, ok := codeMap[kesadaranText]; ok {
		code = c
	}
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "exam", "display": "Exam"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://snomed.info/sct", "code": "1104441000000107", "display": "ACVPU (Alert Confusion Voice Pain Unresponsive) scale score"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueCodeableConcept": M{
			"coding": []M{
				{"system": "http://snomed.info/sct", "code": code, "display": kesadaranText},
			},
		},
	}
}

// BuildObservationBodyWeight builds a body weight observation.
func BuildObservationBodyWeight(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "29463-7", "display": "Body weight"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "kg",
			"system": "http://unitsofmeasure.org",
			"code":   "kg",
		},
	}
}

// BuildObservationBodyHeight builds a body height observation.
func BuildObservationBodyHeight(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "8302-2", "display": "Body height"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "cm",
			"system": "http://unitsofmeasure.org",
			"code":   "cm",
		},
	}
}

// BuildObservationWaistCircumference builds a waist circumference observation.
func BuildObservationWaistCircumference(p ObservationParams, value float64, display string) M {
	return M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs", "display": "Vital Signs"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "8280-0", "display": "Waist Circumference at umbilicus by Tape measure"},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS},
		"performer": []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   display,
		},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
		"valueQuantity": M{
			"value":  value,
			"unit":   "cm",
			"system": "http://unitsofmeasure.org",
			"code":   "cm",
		},
	}
}

// BuildObservationLab builds a laboratory observation.
func BuildObservationLab(p ObservationParams, codeLoinc, displayLoinc, uuidSpecimen, uuidServiceReq string) M {
	obs := M{
		"resourceType": "Observation",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "laboratory", "display": "Laboratory"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": codeLoinc, "display": displayLoinc},
			},
		},
		"subject":           M{"reference": "Patient/" + p.PatientIHS},
		"performer":         []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"encounter":         M{"reference": "urn:uuid:" + p.UUIDEncounter},
		"effectiveDateTime": p.EffectiveTime + p.TimezoneOffset,
		"issued":            p.EffectiveTime + p.TimezoneOffset,
	}
	if uuidSpecimen != "" {
		obs["specimen"] = M{"reference": "urn:uuid:" + uuidSpecimen}
	}
	if uuidServiceReq != "" {
		obs["basedOn"] = []M{{"reference": "urn:uuid:" + uuidServiceReq}}
	}
	return obs
}

// WrapBundleEntry wraps a resource in a FHIR Bundle entry.
func WrapBundleEntry(fullURL string, resource M, method, url string) M {
	return M{
		"fullUrl":  fullURL,
		"resource": resource,
		"request": M{
			"method": method,
			"url":    url,
		},
	}
}

// === Organization ===

// OrganizationParams holds parameters for creating an Organization.
type OrganizationParams struct {
	OrgID       string
	DepCode     string
	Name        string
	Phone       string
	Email       string
	Address     string
	City        string
	PostalCode  string
	Province    string
	Kabupaten   string
	Kecamatan   string
	Kelurahan   string
	PartOf      string
}

// BuildOrganization builds a FHIR Organization resource.
func BuildOrganization(p OrganizationParams) M {
	return M{
		"resourceType": "Organization",
		"active":       true,
		"identifier": []M{
			{
				"use":    "official",
				"system": "http://sys-ids.kemkes.go.id/organization/" + p.OrgID,
				"value":  p.DepCode,
			},
		},
		"type": []M{
			{
				"coding": []M{
					{
						"system":  "http://terminology.hl7.org/CodeSystem/organization-type",
						"code":    "dept",
						"display": "Hospital Department",
					},
				},
			},
		},
		"name": p.Name,
		"telecom": []M{
			{"system": "phone", "value": p.Phone, "use": "work"},
			{"system": "email", "value": p.Email, "use": "work"},
			{"system": "url", "value": "www." + p.Email, "use": "work"},
		},
		"address": []M{
			{
				"use":  "work",
				"type": "both",
				"line": []string{p.Address},
				"city": p.City,
				"postalCode": p.PostalCode,
				"country":    "ID",
				"extension": []M{
					{
						"url": "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
						"extension": []M{
							{"url": "province", "valueCode": p.Province},
							{"url": "city", "valueCode": p.Kabupaten},
							{"url": "district", "valueCode": p.Kecamatan},
							{"url": "village", "valueCode": p.Kelurahan},
						},
					},
				},
			},
		},
		"partOf": M{
			"reference": "Organization/" + p.PartOf,
		},
	}
}

// === Location ===

// LocationParams holds parameters for creating a Location.
type LocationParams struct {
	OrgID       string
	LocCode     string
	Name        string
	Phone       string
	Email       string
	Address     string
	City        string
	PostalCode  string
	Province    string
	Kabupaten   string
	Kecamatan   string
	Kelurahan   string
	Longitude   string
	Latitude    string
	OrgRefID    string
}

// BuildLocation builds a FHIR Location resource.
func BuildLocation(p LocationParams) M {
	return M{
		"resourceType": "Location",
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/location/" + p.OrgID,
				"value":  p.LocCode,
			},
		},
		"status": "active",
		"name":   p.Name,
		"mode":   "instance",
		"telecom": []M{
			{"system": "phone", "value": p.Phone, "use": "work"},
			{"system": "email", "value": p.Email, "use": "work"},
			{"system": "url", "value": "www." + p.Email, "use": "work"},
		},
		"address": M{
			"use":  "work",
			"line": []string{p.Address},
			"city": p.City,
			"postalCode": p.PostalCode,
			"country":    "ID",
			"extension": []M{
				{
					"url": "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
					"extension": []M{
						{"url": "province", "valueCode": p.Province},
						{"url": "city", "valueCode": p.Kabupaten},
						{"url": "district", "valueCode": p.Kecamatan},
						{"url": "village", "valueCode": p.Kelurahan},
					},
				},
			},
		},
		"physicalType": M{
			"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/location-physical-type", "code": "ro", "display": "Room"},
			},
		},
		"position": M{
			"longitude": parseFloat(p.Longitude),
			"latitude":  parseFloat(p.Latitude),
			"altitude":  0,
		},
		"managingOrganization": M{
			"reference": "Organization/" + p.OrgRefID,
		},
	}
}

// === Procedure ===

// ProcedureParams holds parameters for building a Procedure resource.
type ProcedureParams struct {
	UUIDEncounter string
	UUIDProcedure string
	PatientIHS    string
	PatientName   string
	PractitionerID string
	Code          string
	Display       string
	PerformedDateTime string
	TimezoneOffset string
}

// BuildProcedure builds a FHIR Procedure resource.
func BuildProcedure(p ProcedureParams) M {
	return M{
		"resourceType": "Procedure",
		"status":       "completed",
		"category": M{
			"coding": []M{
				{"system": "http://snomed.info/sct", "code": "103693007", "display": "Diagnostic procedure"},
			},
		},
		"code": M{
			"coding": []M{
				{"system": "http://hl7.org/fhir/sid/icd-9-cm", "code": p.Code, "display": p.Display},
			},
		},
		"subject":   M{"reference": "Patient/" + p.PatientIHS, "display": p.PatientName},
		"encounter": M{"reference": "urn:uuid:" + p.UUIDEncounter},
		"performer": []M{
			{"actor": M{"reference": "Practitioner/" + p.PractitionerID}},
		},
		"performedDateTime": p.PerformedDateTime + p.TimezoneOffset,
	}
}

// BuildProcedureBundle wraps a Procedure in a bundle entry.
func BuildProcedureBundle(p ProcedureParams) M {
	return WrapBundleEntry("urn:uuid:"+p.UUIDProcedure, BuildProcedure(p), "POST", "Procedure")
}

// === Medication ===

// MedicationParams holds parameters for building a Medication resource.
type MedicationParams struct {
	UUIDMedication     string
	OrgID              string
	IdentifierValue    string
	KfaCoding          string
	KfaDisplay         string
	FormCoding         string
	FormDisplay        string
	IngredientCoding   string
	IngredientDisplay  string
	NumeratorValue     float64
	NumeratorCode      string
	DenominatorValue   float64
	DenominatorSystem  string
	DenominatorCode    string
}

// BuildMedication builds a FHIR Medication resource.
func BuildMedication(p MedicationParams) M {
	m := M{
		"resourceType": "Medication",
		"meta": M{
			"profile": []string{"https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"},
		},
		"extension": []M{
			{
				"url": "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
				"valueCodeableConcept": M{
					"coding": []M{
						{
							"system":  "http://terminology.kemkes.go.id/CodeSystem/medication-type",
							"code":    "NC",
							"display": "Non-compound",
						},
					},
				},
			},
		},
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/medication/" + p.OrgID,
				"use":    "official",
				"value":  p.IdentifierValue,
			},
		},
		"code": M{
			"coding": []M{
				{"system": "http://sys-ids.kemkes.go.id/kfa", "code": p.KfaCoding, "display": p.KfaDisplay},
			},
		},
		"status": "active",
	}

	if p.FormCoding != "" {
		m["form"] = M{
			"coding": []M{
				{"system": "http://terminology.kemkes.go.id/CodeSystem/medication-form", "code": p.FormCoding, "display": p.FormDisplay},
			},
		}
	}

	if p.IngredientCoding != "" {
		m["ingredient"] = []M{
			{
				"itemCodeableConcept": M{
					"coding": []M{
						{"system": "http://sys-ids.kemkes.go.id/kfa", "code": p.IngredientCoding, "display": p.IngredientDisplay},
					},
				},
				"isActive": true,
				"strength": M{
					"numerator": M{
						"value":  p.NumeratorValue,
						"system": "http://unitsofmeasure.org",
						"code":   p.NumeratorCode,
					},
					"denominator": M{
						"value":  p.DenominatorValue,
						"system": p.DenominatorSystem,
						"code":   p.DenominatorCode,
					},
				},
			},
		}
	}

	return m
}

// === MedicationRequest ===

// MedicationRequestParams holds params for a MedicationRequest.
type MedicationRequestParams struct {
	UUIDMedicationRequest string
	UUIDMedication        string
	OrgID                 string
	IdentifierValue       string
	MedicationRef         string
	PatientIHS            string
	PatientName           string
	TimeAuthored          string
	PractitionerID        string
	PractitionerName      string
	UUIDCondition         string
	UUIDEncounter         string
	ReasonReference       string
	PatientInstruction    string
	TimezoneOffset        string
}

// BuildMedicationRequest builds a FHIR MedicationRequest resource.
func BuildMedicationRequest(p MedicationRequestParams) M {
	m := M{
		"resourceType": "MedicationRequest",
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/prescription/" + p.OrgID,
				"use":    "official",
				"value":  p.IdentifierValue,
			},
		},
		"status":   "completed",
		"intent":   "order",
		"category": []M{{"coding": []M{{"system": "http://terminology.hl7.org/CodeSystem/medicationrequest-category", "code": "outpatient", "display": "Outpatient"}}}},
		"priority": "routine",
		"medicationReference": M{
			"reference": p.MedicationRef,
			"display":   p.IdentifierValue,
		},
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
		},
		"authoredOn": p.TimeAuthored + p.TimezoneOffset,
		"requester": M{
			"reference": "Practitioner/" + p.PractitionerID,
			"display":   p.PractitionerName,
		},
	}

	if p.UUIDCondition != "" {
		m["reasonReference"] = []M{{"reference": "urn:uuid:" + p.UUIDCondition}}
	}
	if p.PatientInstruction != "" {
		m["dosageInstruction"] = []M{{"patientInstruction": p.PatientInstruction}}
	}

	return m
}

// === MedicationDispense ===

// MedicationDispenseParams holds params for a MedicationDispense.
type MedicationDispenseParams struct {
	UUIDDispense          string
	OrgID                 string
	IdentifierValue       string
	UUIDMedication        string
	MedicationName        string
	PatientIHS            string
	PatientName           string
	UUIDEncounter         string
	PractitionerID        string
	PractitionerName      string
	LocationID            string
	UUIDMedicationRequest string
	WhenPrepared          string
	WhenHanded            string
	TimezoneOffset        string
}

// BuildMedicationDispense builds a FHIR MedicationDispense resource.
func BuildMedicationDispense(p MedicationDispenseParams) M {
	return M{
		"resourceType": "MedicationDispense",
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/prescription-item/" + p.OrgID,
				"use":    "official",
				"value":  p.IdentifierValue,
			},
		},
		"status": "completed",
		"category": M{
			"coding": []M{
				{"system": "http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category", "code": "outpatient", "display": "Outpatient"},
			},
		},
		"medicationReference": M{
			"reference": "urn:uuid:" + p.UUIDMedication,
			"display":   p.MedicationName,
		},
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"context": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
		},
		"performer": []M{
			{"actor": M{"reference": "Practitioner/" + p.PractitionerID, "display": p.PractitionerName}},
		},
		"location": M{
			"reference": "Location/" + p.LocationID,
		},
		"authorizingPrescription": []M{
			{"reference": "urn:uuid:" + p.UUIDMedicationRequest},
		},
		"whenPrepared":   p.WhenPrepared + p.TimezoneOffset,
		"whenHandedOver": p.WhenHanded + p.TimezoneOffset,
	}
}

// === ClinicalImpression ===

// ClinicalImpressionParams holds params for ClinicalImpression.
type ClinicalImpressionParams struct {
	UUIDClinicalImpression string
	OrgID                  string
	NoRawat                string
	PatientIHS             string
	PatientName            string
	UUIDEncounter          string
	Description            string
	Status                 string // "completed" or "in-progress"
}

// BuildClinicalImpression builds a FHIR ClinicalImpression resource.
func BuildClinicalImpression(p ClinicalImpressionParams) M {
	return M{
		"resourceType": "ClinicalImpression",
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/clinicalimpression/" + p.OrgID,
				"use":    "official",
				"value":  p.NoRawat,
			},
		},
		"status": p.Status,
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
		},
		"description": p.Description,
	}
}

// === CarePlan ===

// CarePlanParams holds parameters for a CarePlan.
type CarePlanParams struct {
	UUIDCarePlan     string
	EncounterID      string
	PatientIHS       string
	PatientName      string
	PractitionerID   string
	PractitionerName string
	OrgID            string
	NoRawat          string
	Title            string
	Description      string
	EncounterDisplay string
	Created          string
}

// BuildCarePlan builds a FHIR CarePlan resource.
func BuildCarePlan(p CarePlanParams) M {
	return M{
		"resourceType": "CarePlan",
		"identifier": M{
			"system": "http://sys-ids.kemkes.go.id/careplan/" + p.OrgID,
			"value":  p.NoRawat,
		},
		"title":  p.Title,
		"status": "active",
		"category": []M{
			{
				"coding": []M{
					{"system": "http://snomed.info/sct", "code": "736271009", "display": "Outpatient care plan"},
				},
			},
		},
		"intent":      "plan",
		"description": p.Description,
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"encounter": M{
			"reference": "Encounter/" + p.EncounterID,
			"display":   p.EncounterDisplay,
		},
		"created": p.Created,
		"author": M{
			"reference": "Practitioner/" + p.PractitionerID,
			"display":   p.PractitionerName,
		},
	}
}

// === Composition ===

// CompositionParams holds parameters for a Composition resource.
type CompositionParams struct {
	UUIDComposition  string
	UUIDEncounter    string
	PatientIHS       string
	PractitionerID   string
	PatientName      string
	PractitionerName string
	NoRawat          string
	OrgID            string
	EncounterDisplay string
	EffectiveTime    string
	TimezoneOffset   string
}

// BuildComposition builds a FHIR Composition resource for clinical notes.
func BuildComposition(p CompositionParams) M {
	return M{
		"resourceType": "Composition",
		"identifier": M{
			"system": "http://sys-ids.kemkes.go.id/composition/" + p.OrgID,
			"value":  p.NoRawat,
		},
		"status": "final",
		"type": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": "18842-5", "display": "Discharge summary"},
			},
		},
		"subject": M{
			"reference": "Patient/" + p.PatientIHS,
			"display":   p.PatientName,
		},
		"encounter": M{
			"reference": "urn:uuid:" + p.UUIDEncounter,
			"display":   p.EncounterDisplay,
		},
		"date":   p.EffectiveTime + p.TimezoneOffset,
		"author": []M{{"reference": "Practitioner/" + p.PractitionerID, "display": p.PractitionerName}},
		"title":  "Discharge Summary",
		"custodian": M{
			"reference": "Organization/" + p.OrgID,
		},
	}
}

// === ServiceRequest ===

// ServiceRequestParams holds parameters for a ServiceRequest resource.
type ServiceRequestParams struct {
	UUIDServiceRequest string
	UUIDEncounter      string
	PatientIHS         string
	PractitionerID     string
	CodeLoinc          string
	DisplayLoinc       string
	OrgID              string
}

// BuildServiceRequest builds a FHIR ServiceRequest resource.
func BuildServiceRequest(p ServiceRequestParams) M {
	return M{
		"resourceType": "ServiceRequest",
		"identifier": []M{
			{
				"system": "http://sys-ids.kemkes.go.id/servicerequest/" + p.OrgID,
				"value":  p.UUIDServiceRequest,
			},
		},
		"status":     "active",
		"intent":     "original-order",
		"priority":   "routine",
		"subject":    M{"reference": "Patient/" + p.PatientIHS},
		"encounter":  M{"reference": "urn:uuid:" + p.UUIDEncounter},
		"requester":  M{"reference": "Practitioner/" + p.PractitionerID},
		"performer":  []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": p.CodeLoinc, "display": p.DisplayLoinc},
			},
		},
	}
}

// === Specimen ===

// SpecimenParams holds parameters for a Specimen resource.
type SpecimenParams struct {
	UUIDSpecimen       string
	UUIDServiceRequest string
	PatientIHS         string
}

// BuildSpecimen builds a FHIR Specimen resource.
func BuildSpecimen(p SpecimenParams) M {
	return M{
		"resourceType": "Specimen",
		"status":       "available",
		"subject":      M{"reference": "Patient/" + p.PatientIHS},
		"request":      []M{{"reference": "urn:uuid:" + p.UUIDServiceRequest}},
		"type": M{
			"coding": []M{
				{"system": "http://snomed.info/sct", "code": "119297000", "display": "Blood specimen"},
			},
		},
	}
}

// === DiagnosticReport ===

// DiagnosticReportParams holds parameters for a DiagnosticReport resource.
type DiagnosticReportParams struct {
	UUIDReport         string
	UUIDSpecimen       string
	UUIDEncounter      string
	UUIDServiceRequest string
	UUIDObservation    string
	PractitionerID     string
	PatientIHS         string
	CodeLoinc          string
	DisplayLoinc       string
	ResultTime         string
	TimezoneOffset     string
}

// BuildDiagnosticReport builds a FHIR DiagnosticReport resource.
func BuildDiagnosticReport(p DiagnosticReportParams) M {
	return M{
		"resourceType": "DiagnosticReport",
		"status":       "final",
		"category": []M{
			{"coding": []M{
				{"system": "http://terminology.hl7.org/CodeSystem/v2-0074", "code": "LAB", "display": "Laboratory"},
			}},
		},
		"code": M{
			"coding": []M{
				{"system": "http://loinc.org", "code": p.CodeLoinc, "display": p.DisplayLoinc},
			},
		},
		"subject":           M{"reference": "Patient/" + p.PatientIHS},
		"encounter":         M{"reference": "urn:uuid:" + p.UUIDEncounter},
		"effectiveDateTime": p.ResultTime + p.TimezoneOffset,
		"issued":            p.ResultTime + p.TimezoneOffset,
		"performer":         []M{{"reference": "Practitioner/" + p.PractitionerID}},
		"specimen":          M{"reference": "urn:uuid:" + p.UUIDSpecimen},
		"basedOn":           []M{{"reference": "urn:uuid:" + p.UUIDServiceRequest}},
		"result":            []M{{"reference": "urn:uuid:" + p.UUIDObservation}},
	}
}

// === AllergyIntolerance ===

// AllergyParams holds parameters for allergies.
type AllergyParams struct {
	PatientIHS     string
	PatientName    string
	PractitionerID string
	UUIDEncounter  string
	Code           string
	Display        string
	Category       string // food, medication, environment
}

// BuildAllergyIntolerance builds a FHIR AllergyIntolerance resource.
func BuildAllergyIntolerance(p AllergyParams) M {
	return M{
		"resourceType":    "AllergyIntolerance",
		"clinicalStatus":  M{"coding": []M{{"system": "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical", "code": "active", "display": "Active"}}},
		"verificationStatus": M{"coding": []M{{"system": "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification", "code": "confirmed", "display": "Confirmed"}}},
		"category":        []string{p.Category},
		"code":            M{"coding": []M{{"system": "http://snomed.info/sct", "code": p.Code, "display": p.Display}}},
		"patient":         M{"reference": "Patient/" + p.PatientIHS, "display": p.PatientName},
		"encounter":       M{"reference": "urn:uuid:" + p.UUIDEncounter},
		"recorder":        M{"reference": "Practitioner/" + p.PractitionerID},
	}
}

// === Bundle ===

// BuildBundle constructs a FHIR transaction bundle.
func BuildBundle(entries []M) M {
	return M{
		"resourceType": "Bundle",
		"type":         "transaction",
		"entry":        entries,
	}
}

// BuildEncounterDiagnosis generates the encounter.diagnosis array for multiple diagnoses.
func BuildEncounterDiagnosis(diagnoses []struct {
	UUID      string
	Name      string
	IsPrimary bool
}) []M {
	var items []M
	for _, d := range diagnoses {
		rank := 2
		if d.IsPrimary {
			rank = 1
		}
		items = append(items, M{
			"condition": M{
				"reference": "urn:uuid:" + d.UUID,
				"display":   d.Name,
			},
			"use": M{
				"coding": []M{
					{
						"system":  "http://terminology.hl7.org/CodeSystem/diagnosis-role",
						"code":    "DD",
						"display": "Discharge diagnosis",
					},
				},
			},
			"rank": rank,
		})
	}
	return items
}

// ToJSON serializes a value to indented JSON.
func ToJSON(v interface{}) ([]byte, error) {
	return json.MarshalIndent(v, "", "  ")
}

// ToJSONCompact serializes a value to compact JSON.
func ToJSONCompact(v interface{}) ([]byte, error) {
	return json.Marshal(v)
}

// FormatICD9Code inserts a dot into ICD-9 codes if missing.
func FormatICD9Code(code string) string {
	if code == "" || strings.Contains(code, ".") || len(code) < 2 {
		return code
	}
	return code[:2] + "." + code[2:]
}

func parseFloat(s string) float64 {
	var f float64
	fmt.Sscanf(s, "%f", &f)
	return f
}
