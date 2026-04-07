FROM golang:1.24 AS builder
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o pixelpong .

FROM scratch
COPY --from=builder /app/pixelpong /pixelpong
EXPOSE 4432
ENTRYPOINT ["/pixelpong"]
