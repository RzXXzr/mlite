package web

import (
	"embed"
	"html/template"
	"io"
	"path/filepath"
	"strings"
)

//go:embed templates/*.html
var templateFS embed.FS

// TemplateEngine manages HTML templates.
type TemplateEngine struct {
	templates map[string]*template.Template
}

// NewTemplateEngine parses and caches all templates.
func NewTemplateEngine() (*TemplateEngine, error) {
	te := &TemplateEngine{
		templates: make(map[string]*template.Template),
	}

	funcMap := template.FuncMap{
		"safeHTML": func(s string) template.HTML { return template.HTML(s) },
		"safeJS":  func(s string) template.JS { return template.JS(s) },
		"safeURL": func(s string) template.URL { return template.URL(s) },
		"lower":   strings.ToLower,
		"upper":   strings.ToUpper,
		"add": func(a, b int) int { return a + b },
		"sub": func(a, b int) int { return a - b },
		"mul": func(a, b int) int { return a * b },
		"pct": func(a, b int) int {
			if b == 0 {
				return 0
			}
			return a * 100 / b
		},
		"eq": func(a, b interface{}) bool {
			return a == b
		},
		"ne": func(a, b interface{}) bool {
			return a != b
		},
		"gt": func(a, b int) bool {
			return a > b
		},
		"empty": func(s string) bool {
			return s == "" || s == "0000-00-00"
		},
		"notEmpty": func(s string) bool {
			return s != "" && s != "0000-00-00" && s != "-"
		},
		"default": func(def, val string) string {
			if val == "" {
				return def
			}
			return val
		},
	}

	layoutContent, err := templateFS.ReadFile("templates/layout.html")
	if err != nil {
		return nil, err
	}

	entries, err := templateFS.ReadDir("templates")
	if err != nil {
		return nil, err
	}

	for _, entry := range entries {
		if entry.IsDir() || entry.Name() == "layout.html" {
			continue
		}

		name := strings.TrimSuffix(entry.Name(), filepath.Ext(entry.Name()))

		pageContent, err := templateFS.ReadFile("templates/" + entry.Name())
		if err != nil {
			return nil, err
		}

		combined := string(layoutContent) + "\n" + string(pageContent)

		tmpl, err := template.New(name).Funcs(funcMap).Parse(combined)
		if err != nil {
			return nil, err
		}

		te.templates[name] = tmpl
	}

	return te, nil
}

// Render renders a named template with data to the writer.
func (te *TemplateEngine) Render(w io.Writer, name string, data interface{}) error {
	tmpl, ok := te.templates[name]
	if !ok {
		return &TemplateNotFoundError{Name: name}
	}
	return tmpl.ExecuteTemplate(w, "layout", data)
}

// TemplateNotFoundError is returned when a template is not found.
type TemplateNotFoundError struct {
	Name string
}

func (e *TemplateNotFoundError) Error() string {
	return "template not found: " + e.Name
}
