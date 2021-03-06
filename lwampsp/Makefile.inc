ifeq ($(shell uname -s), Linux)
GL_LIBS=-lglui -lglut -lGLU -lGL
else
GL_LIBS=-lglui -lglut32 -lglu32 -lopengl32
endif

CC=g++
AR=ar

SDL_CFLAGS=$(shell sdl-config --cflags)
SDL_LIBS=$(shell sdl-config --libs)

CFLAGS=-ggdb -DHAVE_OPENGL -I../lwalib $(SDL_CFLAGS) # -Wall
LDFLAGS=$(SDL_LIBS) $(GL_LIBS)

COMPILE=$(CC) $(CFLAGS) -c $< -o $@
LINK=$(CC) $(CFLAGS) -o $@ $(OBJS) ../lwalib/lwalib.a  $(LDFLAGS)
MKLIB=$(AR) r $@ $(OBJS)

clean:
	rm -f *.o *.exe $(APP)

lwalib:
	$(MAKE) -C ../lwalib

%.o: %.c
	$(COMPILE)

%.a: $(OBJS)
	$(MKLIB)

$(APP): $(OBJS) lwalib
	$(LINK)


.PHONY: clean lwalib
